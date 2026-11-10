#include "ElfImg.h"
#include <fcntl.h>
#include <sys/mman.h>
#include <unistd.h>
#include <cstring>
#include "android/log.h"

#define LOG_TAG "ElfImg"
#define LOGE(...) __android_log_print(ANDROID_LOG_ERROR, LOG_TAG, __VA_ARGS__)

namespace SandHook {

ElfImg::ElfImg(const char *elf_path) : elfPath(elf_path ? elf_path : ""), baseAddress(nullptr), fileSize(0), elfHeader(nullptr), sectionHeaders(nullptr), sectionStringTable(nullptr) {
    if (!elfPath.empty() && !loadElf()) {
        LOGE("Failed to load ELF file: %s", elf_path);
    }
}

ElfImg::~ElfImg() {
    if (baseAddress && fileSize > 0) {
        munmap(baseAddress, fileSize);
    }
}

bool ElfImg::loadElf() {
    int fd = open(elfPath.c_str(), O_RDONLY);
    if (fd < 0) {
        LOGE("Could not open ELF file: %s", elfPath.c_str());
        return false;
    }

    off_t sz = lseek(fd, 0, SEEK_END);
    if (sz <= (off_t)sizeof(Elf64_Ehdr)) {
        close(fd);
        return false;
    }
    fileSize = static_cast<size_t>(sz);
    baseAddress = mmap(nullptr, fileSize, PROT_READ, MAP_PRIVATE, fd, 0);
    close(fd);

    if (baseAddress == MAP_FAILED) {
        LOGE("mmap failed");
        baseAddress = nullptr;
        return false;
    }

    elfHeader = reinterpret_cast<Elf64_Ehdr*>(baseAddress);
    if (memcmp(elfHeader->e_ident, ELFMAG, SELFMAG) != 0) {
        LOGE("Not a valid ELF file");
        munmap(baseAddress, fileSize);
        baseAddress = nullptr;
        elfHeader = nullptr;
        return false;
    }

    if (elfHeader->e_shoff == 0
            || elfHeader->e_shnum == 0
            || (size_t)(elfHeader->e_shoff + (size_t)elfHeader->e_shnum * sizeof(Elf64_Shdr)) > fileSize
            || elfHeader->e_shstrndx >= elfHeader->e_shnum) {
        sectionHeaders = nullptr;
        sectionStringTable = nullptr;
        return true;
    }

    sectionHeaders = reinterpret_cast<Elf64_Shdr*>((char*)baseAddress + elfHeader->e_shoff);
    size_t strOff = sectionHeaders[elfHeader->e_shstrndx].sh_offset;
    if (strOff < fileSize) {
        sectionStringTable = (char*)baseAddress + strOff;
    }

    return true;
}

void* ElfImg::findSymbol(const char* symbol_name) {
    if (!symbol_name || !baseAddress || !elfHeader || !sectionHeaders) {
        return nullptr;
    }
    for (int i = 0; i < elfHeader->e_shnum; ++i) {
        Elf64_Shdr* shdr = &sectionHeaders[i];
        if (shdr->sh_type == SHT_SYMTAB || shdr->sh_type == SHT_DYNSYM) {
            if (shdr->sh_entsize == 0 || (size_t)(shdr->sh_offset + shdr->sh_size) > fileSize) continue;
            if (shdr->sh_link >= elfHeader->e_shnum) continue;
            size_t linkOff = sectionHeaders[shdr->sh_link].sh_offset;
            if (linkOff >= fileSize) continue;

            Elf64_Sym* symbols = (Elf64_Sym*)((char*)baseAddress + shdr->sh_offset);
            int symbolCount = shdr->sh_size / shdr->sh_entsize;
            char* strtab = (char*)baseAddress + linkOff;

            for (int j = 0; j < symbolCount; ++j) {
                if (linkOff + symbols[j].st_name >= fileSize) continue;
                char* current_symbol = strtab + symbols[j].st_name;
                if (strcmp(symbol_name, current_symbol) == 0) {
                    return (void*)((uintptr_t)symbols[j].st_value);
                }
            }
        }
    }
    return nullptr;
}

void* ElfImg::getSymbAddress(const char* symbol_name) {
    return findSymbol(symbol_name);
}

} // namespace SandHook
