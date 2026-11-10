#include "KittyMemory.h"
#include <climits>
#include <cstdlib>
#include <cstring>
#include <fcntl.h>
#include <mutex>
#include <sys/mman.h>
#include <sys/syscall.h>
#include <sys/uio.h>

using KittyMemory::Memory_Status;
using KittyMemory::ProcMap;

struct mapsCache {
    std::string identifier;
    ProcMap map;
};

static std::vector<mapsCache> __mapsCache;
static std::mutex __mapsCacheMutex;

static ProcMap findMapInCache(const std::string &id) {
    std::lock_guard<std::mutex> lock(__mapsCacheMutex);
    ProcMap ret{};
    for (size_t i = 0; i < __mapsCache.size(); i++) {
        if (__mapsCache[i].identifier == id) {
            ret = __mapsCache[i].map;
            break;
        }
    }
    return ret;
}

static bool inspectAddressMapping(const void *addr, size_t len, bool *outHasWrite, bool *outHasExec) {
    if (outHasWrite) *outHasWrite = false;
    if (outHasExec) *outHasExec = false;
    if (addr == nullptr || len == 0) return false;

    uintptr_t targetStart = reinterpret_cast<uintptr_t>(addr);
    uintptr_t targetEnd = targetStart + len;
    if (targetEnd < targetStart) return false;

    FILE *fp = fopen("/proc/self/maps", "rt");
    if (!fp) return true;

    char line[512];
    uintptr_t coveredUntil = targetStart;
    while (fgets(line, sizeof(line), fp)) {
        unsigned long long segStart = 0, segEnd = 0;
        char perms[8] = {0};
        if (sscanf(line, "%llx-%llx %7s", &segStart, &segEnd, perms) == 3) {
            if (segEnd <= coveredUntil) continue;
            if (segStart > coveredUntil) {
                break;
            }
            if (strstr(perms, "---") != nullptr) {
                break;
            }
            if (strchr(perms, 'w') != nullptr && outHasWrite) {
                *outHasWrite = true;
            }
            if (strchr(perms, 'x') != nullptr && outHasExec) {
                *outHasExec = true;
            }
            coveredUntil = static_cast<uintptr_t>(segEnd);
            if (coveredUntil >= targetEnd) {
                fclose(fp);
                return true;
            }
        }
    }
    fclose(fp);
    return coveredUntil >= targetEnd;
}

bool KittyMemory::ProtectAddr(void *addr, size_t length, int protection) {
    if (addr == nullptr || length == 0) return false;
    long pageSize = sysconf(_SC_PAGESIZE);
    if (pageSize <= 0) pageSize = 4096;
    uintptr_t uAddr = reinterpret_cast<uintptr_t>(addr);
    uintptr_t pageStart = uAddr & ~static_cast<uintptr_t>(pageSize - 1);
    uintptr_t pageEnd = (uAddr + length + static_cast<uintptr_t>(pageSize) - 1) & ~static_cast<uintptr_t>(pageSize - 1);
    size_t alignedLen = pageEnd - pageStart;

    if (syscall(__NR_mprotect, reinterpret_cast<void *>(pageStart), alignedLen, protection) == 0) {
        return true;
    }

    // Android 10-16 SELinux `app_data_file:execmod` fallback:
    // If mprotect with PROT_EXEC fails on a file-backed virtualized library page,
    // remap each page in-place as MAP_PRIVATE | MAP_ANONYMOUS | MAP_FIXED so PROT_EXEC is NEVER lost!
    if (alignedLen <= 4 * 1024 * 1024) {
        uint8_t *backup = static_cast<uint8_t *>(malloc(alignedLen));
        if (backup) {
            syscall(__NR_mprotect, reinterpret_cast<void *>(pageStart), alignedLen, PROT_READ | PROT_EXEC);
            syscall(__NR_mprotect, reinterpret_cast<void *>(pageStart), alignedLen, PROT_READ);
            memcpy(backup, reinterpret_cast<const void *>(pageStart), alignedLen);
            void *mapped = mmap(reinterpret_cast<void *>(pageStart), alignedLen,
                                PROT_READ | PROT_WRITE | PROT_EXEC,
                                MAP_PRIVATE | MAP_ANONYMOUS | MAP_FIXED, -1, 0);
            if (mapped != MAP_FAILED) {
                memcpy(reinterpret_cast<void *>(pageStart), backup, alignedLen);
                __builtin___clear_cache(reinterpret_cast<char *>(pageStart),
                                        reinterpret_cast<char *>(pageStart + alignedLen));
                free(backup);
                syscall(__NR_mprotect, reinterpret_cast<void *>(pageStart), alignedLen,
                        protection | PROT_READ | PROT_EXEC);
                return true;
            }
            free(backup);
        }
    }
    return false;
}

Memory_Status KittyMemory::memWrite(void *addr, const void *buffer, size_t len) {
    if (addr == NULL)
        return INV_ADDR;
    if (buffer == NULL)
        return INV_BUF;
    if (len < 1 || len > INT_MAX)
        return INV_LEN;

    bool origWritable = false;
    bool origExecutable = false;
    if (!inspectAddressMapping(addr, len, &origWritable, &origExecutable)) {
        return INV_ADDR;
    }

    if (!ProtectAddr(addr, len, _PROT_RWX_)) {
        return INV_PROT;
    }

    if (memcpy(addr, buffer, len) != NULL) {
        __builtin___clear_cache(reinterpret_cast<char *>(addr), reinterpret_cast<char *>(addr) + len);
        int restoreProt = _PROT_RX_;
        if (origWritable) {
            restoreProt = origExecutable ? _PROT_RWX_ : (PROT_READ | PROT_WRITE);
        } else if (sysconf(_SC_PAGESIZE) > 4096) {
            restoreProt = _PROT_RWX_;
        }
        ProtectAddr(addr, len, restoreProt);
        return SUCCESS;
    }
    return FAILED;
}

Memory_Status KittyMemory::memRead(void *buffer, const void *addr, size_t len) {
    if (addr == NULL)
        return INV_ADDR;
    if (buffer == NULL)
        return INV_BUF;
    if (len < 1 || len > INT_MAX)
        return INV_LEN;

    bool origWritable = false;
    bool origExecutable = false;
    if (!inspectAddressMapping(addr, len, &origWritable, &origExecutable)) {
        return INV_ADDR;
    }

    int readProt = origWritable
            ? (origExecutable ? _PROT_RWX_ : (PROT_READ | PROT_WRITE))
            : (origExecutable ? _PROT_RX_ : PROT_READ);
    ProtectAddr(const_cast<void *>(addr), len, readProt);

    if (memcpy(buffer, addr, len) != NULL)
        return SUCCESS;
    return FAILED;
}

std::string KittyMemory::read2HexStr(const void *addr, size_t len) {
    if (addr == nullptr || len == 0 || len > INT_MAX / 2)
        return {};
    std::vector<unsigned char> bytes(len, 0);
    if (memRead(bytes.data(), addr, len) != SUCCESS)
        return {};
    static constexpr char HEX[] = "0123456789ABCDEF";
    std::string result(len * 2, '0');
    for (size_t i = 0; i < len; ++i) {
        result[i * 2] = HEX[bytes[i] >> 4];
        result[i * 2 + 1] = HEX[bytes[i] & 0x0F];
    }
    return result;
}

ProcMap KittyMemory::getLibraryMap(const char *libraryName) {
    ProcMap retMap{};
    retMap.startAddr = nullptr;
    retMap.endAddr = nullptr;
    retMap.length = 0;
    retMap.offset = 0;
    retMap.inode = 0;
    if (!libraryName || libraryName[0] == '\0') return retMap;

    char line[512] = {0};
    FILE *fp = fopen("/proc/self/maps", "rt");
    if (fp != NULL) {
        bool foundBase = false;
        while (fgets(line, sizeof(line), fp)) {
            if (strstr(line, libraryName)) {
                unsigned long long startAddr = 0, endAddr = 0;
                long segOffset = 0;
                int segInode = 0;
                char tmpPerms[8] = {0}, tmpDev[16] = {0}, tmpPathname[444] = {0};
                int parsed = sscanf(line, "%llx-%llx %7s %lx %15s %d %443s",
                       &startAddr, &endAddr,
                       tmpPerms, &segOffset, tmpDev, &segInode, tmpPathname);
                if (parsed < 6) continue;
                if (strstr(tmpPerms, "---") != nullptr) {
                    continue;
                }
                // Normalize both r--p (Segment 0) and --xp (Segment 1) to _PROT_RX_
                if (strchr(tmpPerms, 'w') == nullptr && (strchr(tmpPerms, 'r') == nullptr || strchr(tmpPerms, 'x') == nullptr)) {
                    ProtectAddr(reinterpret_cast<void *>(static_cast<uintptr_t>(startAddr)),
                                static_cast<size_t>(endAddr - startAddr),
                                _PROT_RX_);
                }
                if (!foundBase) {
                    uintptr_t trueBase = static_cast<uintptr_t>(startAddr) - static_cast<uintptr_t>(segOffset);
                    retMap.startAddr = reinterpret_cast<void *>(trueBase);
                    retMap.endAddr   = reinterpret_cast<void *>(static_cast<uintptr_t>(endAddr));
                    retMap.length    = static_cast<size_t>(endAddr - trueBase);
                    retMap.offset    = 0;
                    retMap.perms     = tmpPerms;
                    retMap.dev       = tmpDev;
                    retMap.inode     = segInode;
                    retMap.pathname  = tmpPathname;
                    foundBase = true;
                } else {
                    retMap.endAddr = reinterpret_cast<void *>(static_cast<uintptr_t>(endAddr));
                    retMap.length  = static_cast<size_t>(endAddr - reinterpret_cast<uintptr_t>(retMap.startAddr));
                }
            }
        }
        fclose(fp);
    }
    return retMap;
}

uintptr_t KittyMemory::getAbsoluteAddress(const char *libraryName, uintptr_t relativeAddr, bool useCache) {
    if (!libraryName || relativeAddr == 0) return 0;
    ProcMap libMap;
    if (useCache) {
        libMap = findMapInCache(libraryName);
        if (libMap.isValid()) {
            return (reinterpret_cast<uintptr_t>(libMap.startAddr) + relativeAddr);
        }
    }
    libMap = getLibraryMap(libraryName);
    if (!libMap.isValid())
        return 0;
    if (useCache) {
        std::lock_guard<std::mutex> lock(__mapsCacheMutex);
        mapsCache cachedMap;
        cachedMap.identifier = libraryName;
        cachedMap.map        = libMap;
        __mapsCache.push_back(cachedMap);
    }
    return (reinterpret_cast<uintptr_t>(libMap.startAddr) + relativeAddr);
}
