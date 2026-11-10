#include <sys/types.h>
#include <cstdio>
#include <sys/mman.h>
#include <elf.h>
#include <cstring>
#include <cstdlib>
#include <unistd.h>
#include <fcntl.h>
#include "fake_dlfcn.h"

#ifdef __arm__
#define Elf_Ehdr Elf32_Ehdr
#define Elf_Shdr Elf32_Shdr
#define Elf_Sym  Elf32_Sym
#elif defined(__i386__)
#define Elf_Ehdr Elf32_Ehdr
#define Elf_Shdr Elf32_Shdr
#define Elf_Sym  Elf32_Sym
#elif defined(__aarch64__) || defined(__x86_64__)
#define Elf_Ehdr Elf64_Ehdr
#define Elf_Shdr Elf64_Shdr
#define Elf_Sym  Elf64_Sym
#else
#error "Arch unknown, please port me"
#endif

struct ctx {
    intptr_t load_addr;
    void *dynstr;
    void *dynsym;
    int nsyms;
    off_t bias;
};

int fake_dlclose(void *handle) {
    if (handle) {
        struct ctx *ctx = (struct ctx *) handle;
        if (ctx->dynsym) free(ctx->dynsym);
        if (ctx->dynstr) free(ctx->dynstr);
        free(ctx);
    }
    return 0;
}

void *fake_dlopen(const char *libpath, int flags) {
    FILE *maps;
    char buff[512];
    struct ctx *ctx = 0;
    unsigned long long start_addr = 0, end_addr = 0, file_off = 0;
    char perms[8] = {0};
    char fullpath[384] = {0};
    off_t load_addr = 0, size = 0;
    int k, fd = -1, found = 0;
    intptr_t shoff;
    Elf_Ehdr *elf = (Elf_Ehdr *) MAP_FAILED;

    if (!libpath || libpath[0] == '\0') return 0;

    maps = fopen("/proc/self/maps", "r");
    if (!maps) goto err_exit;

    while (fgets(buff, sizeof(buff), maps)) {
        if (!strstr(buff, libpath)) continue;
        int n = sscanf(buff, "%llx-%llx %7s %llx %*s %*s %383s",
                       &start_addr, &end_addr, perms, &file_off, fullpath);
        if (n >= 4 && (strstr(perms, "r-xp") || strstr(perms, "--xp") || strstr(perms, "r--p"))) {
            // Always compute true ELF base address (start_addr - file_off) so libanogs.so Segment 1
            // never shifts the base address!
            load_addr = static_cast<off_t>(start_addr - file_off);
            found = 1;
            break;
        }
    }
    fclose(maps);

    if (!found) goto err_exit;

    fd = open(fullpath[0] != '\0' ? fullpath : libpath, O_RDONLY);
    if (fd < 0) fd = open(libpath, O_RDONLY);
    if (fd < 0) goto err_exit;

    size = lseek(fd, 0, SEEK_END);
    if (size <= static_cast<off_t>(sizeof(Elf_Ehdr))) goto err_exit;

    elf = (Elf_Ehdr *) mmap(0, size, PROT_READ, MAP_SHARED, fd, 0);
    close(fd);
    fd = -1;

    if (elf == MAP_FAILED) goto err_exit;
    if (elf->e_shoff == 0 || (off_t)(elf->e_shoff + (size_t)elf->e_shnum * elf->e_shentsize) > size) {
        goto err_exit;
    }

    ctx = (struct ctx *) calloc(1, sizeof(struct ctx));
    if (!ctx) goto err_exit;

    ctx->load_addr = (intptr_t) load_addr;
    shoff = (intptr_t) elf + elf->e_shoff;

    for (k = 0; k < elf->e_shnum; k++, shoff += elf->e_shentsize) {
        Elf_Shdr *sh = (Elf_Shdr *) shoff;
        if ((off_t)(sh->sh_offset + sh->sh_size) > size) continue;
        switch (sh->sh_type) {
            case SHT_DYNSYM:
                if (ctx->dynsym) break;
                ctx->dynsym = malloc(sh->sh_size);
                if (!ctx->dynsym) goto err_exit;
                memcpy(ctx->dynsym, (const void *) ((intptr_t) elf + sh->sh_offset), sh->sh_size);
                ctx->nsyms = (sh->sh_size / sizeof(Elf_Sym));
                break;

            case SHT_STRTAB:
                if (ctx->dynstr) break;
                ctx->dynstr = malloc(sh->sh_size);
                if (!ctx->dynstr) goto err_exit;
                memcpy(ctx->dynstr, (const void *) (((intptr_t) elf) + sh->sh_offset), sh->sh_size);
                break;

            case SHT_PROGBITS:
                if (!ctx->dynstr || !ctx->dynsym) break;
                ctx->bias = (off_t) sh->sh_addr - (off_t) sh->sh_offset;
                k = elf->e_shnum;
                break;
        }
    }

    munmap(elf, (size_t) size);
    elf = (Elf_Ehdr *) MAP_FAILED;

    if (!ctx->dynstr || !ctx->dynsym) goto err_exit;
    return ctx;

err_exit:
    if (fd >= 0) close(fd);
    if (elf != MAP_FAILED) munmap(elf, size);
    fake_dlclose(ctx);
    return 0;
}

void *fake_dlsym(void *handle, const char *name) {
    if (!handle || !name) return 0;
    int k;
    struct ctx *ctx = (struct ctx *) handle;
    Elf_Sym *sym = (Elf_Sym *) ctx->dynsym;
    char *strings = (char *) ctx->dynstr;
    if (!sym || !strings) return 0;

    for (k = 0; k < ctx->nsyms; k++, sym++) {
        if (strcmp(strings + sym->st_name, name) == 0) {
            void *ret = (void *) (ctx->load_addr + sym->st_value - ctx->bias);
            return ret;
        }
    }
    return 0;
}
