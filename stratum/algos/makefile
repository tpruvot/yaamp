
CC=gcc

#CFLAGS=-c -g -I /usr/include/mysql
#LDFLAGS=-g

CXXFLAGS = -O2 -I.. -march=native
CFLAGS= $(CXXFLAGS) -std=gnu99
LDFLAGS=-O2 -lgmp

SOURCES=lyra2re.c lyra2v2.c lyra2v3.c Lyra2.c lyra2z.c Lyra2-z.c lyra2zz.c Lyra2-zz.c lyra2z330.c lyra2vc0ban.c Sponge.c allium.c \
	c11.c x11.c x12.c x13.c hsr14.c x14.c x15.c x17.c x17r.c x18.c \
	x22i.c SWIFFTX/SWIFFTX.c lane.c x25x.c \
	blake.c blakecoin.c blake2b.c blake2s.c blake2-ref/blake2b.c blake2-ref/blake2s.c jha.c keccak.c lbry.c tribus.c exosis.c \
	deep.c fresh.c groestl.c neoscrypt.c nist5.c quark.c qubit.c skein.c skein2.c bmw512.c \
	bitcore.c timetravel.c x11evo.c x16r.c x16s.c x16rt.c x16rv2.c xevan.c bastion.c hmq17.c sonoa.c \
	bmw.c luffa.c pentablake.c vitalium.c whirlpool.c whirlpoolx.c zr5.c \
	scrypt.c scryptn.c sha256.c sha256t.c sha256q.c sha256-d.c minotaur.c \
	a5a.c a5amath.c m7m.c magimath.cpp velvet.c \
	blake2/blake2b.c argon2d/argon2.c argon2d/core.c argon2d/encoding.c argon2d/opt.c argon2d/thread.c \
	hive.c pomelo.c hex.c argon2m.c argon2d.c geek.c balloon.c \
	phi.c phi2.c polytimos.c rainforest.c skunk.c sib.c veltor.c gost.c aergo.c x20r.c x21s.c lbk3.c pipehash.c gltalgos.c \
	yespower/yespower-platform.c yespower/yespower-combined.c sha256-P.c dedal.c phi1612.c bcd.c \

OBJECTS=$(SOURCES:%.c=%.o) $(SOURCES:%.cpp=%.o) $(SOURCES:%.cc=%.o)
OUTPUT=libalgos.a

all: $(SOURCES) $(OUTPUT)

$(OUTPUT): $(OBJECTS)
	ar rc $@ $(OBJECTS)
	touch ../stratum.cpp

.cpp.o:
	$(CC) $(CXXFLAGS) -c $< -o $@

.c.o:
	$(CC) $(CFLAGS) -c $< -o $@

#	$(CC) $(CFLAGS) -std=gnu99 -Wno-pointer-sign -Wno-pointer-to-int-cast -funroll-loops -fvariable-expansion-in-unroller -fmerge-all-constants -fbranch-target-load-optimize2 -fsched2-use-superblocks -falign-loops=16 -falign-functions=16 -falign-jumps=16 -falign-labels=16 -Ofast -flto -fuse-linker-plugin -ftree-loop-if-convert-stores -DUSE_ASM -pg $<

clean:
	rm -f *.o
	rm -f ar2/*.o
	rm -f blake2/*.o
	rm -f SWIFFTX/*.o
