
CC=gcc

CFLAGS=-c -g -I /usr/include/mysql -march=native
LDFLAGS=-g

#CFLAGS=-c -O2 -I /usr/include/mysql
#LDFLAGS=-O2

LDLIBS=-lstdc++

SOURCES=blocknotify.cpp

OBJECTS=$(SOURCES:.cpp=.o)
OUTPUT=blocknotify

all: $(SOURCES) $(OUTPUT)

install: all
	strip -s $(OUTPUT)
	mv $(OUTPUT) ../bin/

clean:
	rm -f *.o
	rm -f $(OUTPUT)
