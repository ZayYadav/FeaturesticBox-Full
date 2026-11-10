#define SubstrateInternal
#include "CydiaSubstrate.h"
#include "SubstrateLog.hpp"

#include <sys/mman.h>
#include <errno.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <unistd.h>

extern "C" void __clear_cache(void *beg, void *end);

struct __SubstrateMemory {
    void *address_;
    size_t width_;

    __SubstrateMemory(void *address, size_t width) :
        address_(address),
        width_(width)
    {
    }
};

extern "C" SubstrateMemoryRef SubstrateMemoryCreate(SubstrateAllocatorRef allocator, SubstrateProcessRef process, void *data, size_t size) {
    if (allocator != NULL || data == NULL || size == 0) {
        return NULL;
    }

    long page = sysconf(_SC_PAGESIZE);
    if (page <= 0) page = 4096;

    uintptr_t base = (reinterpret_cast<uintptr_t>(data) / page) * page;
    size_t width = (((reinterpret_cast<uintptr_t>(data) + size - 1) / page) + 1) * page - base;
    void *address = reinterpret_cast<void *>(base);

    if (mprotect(address, width, PROT_READ | PROT_WRITE | PROT_EXEC) == -1) {
        if (width <= 1024 * 1024) {
            void *backup = malloc(width);
            if (backup != NULL) {
                mprotect(address, width, PROT_READ | PROT_EXEC);
                mprotect(address, width, PROT_READ);
                memcpy(backup, address, width);
                void *mapped = mmap(address, width, PROT_READ | PROT_WRITE | PROT_EXEC,
                                    MAP_PRIVATE | MAP_ANONYMOUS | MAP_FIXED, -1, 0);
                if (mapped != MAP_FAILED) {
                    memcpy(address, backup, width);
                    __clear_cache(reinterpret_cast<char *>(address), reinterpret_cast<char *>(address) + width);
                    free(backup);
                    return new __SubstrateMemory(address, width);
                }
                free(backup);
            }
        }
        return NULL;
    }

    return new __SubstrateMemory(address, width);
}

extern "C" void SubstrateMemoryRelease(SubstrateMemoryRef memory) {
    if (memory == NULL) return;
    if (mprotect(memory->address_, memory->width_, PROT_READ | PROT_EXEC) == -1) {
        mprotect(memory->address_, memory->width_, PROT_READ | PROT_WRITE | PROT_EXEC);
    }
    __clear_cache(reinterpret_cast<char *>(memory->address_), reinterpret_cast<char *>(memory->address_) + memory->width_);
    delete memory;
}
