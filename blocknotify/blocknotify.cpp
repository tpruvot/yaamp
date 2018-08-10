
#include <sys/types.h>
#include <sys/socket.h>
#include <sys/time.h>
#include <sys/resource.h>
#include <netinet/in.h>
#include <arpa/inet.h>

#include <netdb.h>
#include <errno.h>
#include <math.h>
#include <unistd.h>
#include <fcntl.h>
#include <stdio.h>
#include <stdlib.h>
#include <stdarg.h>
#include <string.h>
#include <unistd.h>
#include <pthread.h>

// program yaamp.com:port coinid blockhash

int main(int argc, char **argv)
{
	if(argc < 4)
	{
		printf("usage: blocknotify server:port coinid blockhash\n");
		return 1;
	}

	char *p = strchr(argv[1], ':');
	if(!p)
	{
		printf("usage: blocknotify server:port coinid blockhash\n");
		return 1;
	}

	int port = atoi(p+1);
	*p = 0;

	int coinid = atoi(argv[2]);
	char blockhash[1024];
	strncpy(blockhash, argv[3], 1024);

	int sock = socket(AF_INET, SOCK_STREAM, 0);
	if(sock <= 0)
	{
		printf("error socket %s id %s\n", argv[1], argv[2]);
		return 1;
	}

	struct hostent *ent = gethostbyname(argv[1]);
	if(!ent)
	{
		printf("error gethostbyname %s id %s\n", argv[1], argv[2]);
		return 1;
	}

	struct sockaddr_in serv;

	serv.sin_family = AF_INET;
	serv.sin_port = htons(port);

	bcopy((char *)ent->h_addr, (char *)&serv.sin_addr.s_addr, ent->h_length);

	int res = connect(sock, (struct sockaddr*)&serv, sizeof(serv));
	if(res < 0)
	{
		printf("error connect %s id %s\n", argv[1], argv[2]);
		return 1;
	}

	char buffer[1024];
	sprintf(buffer, "{\"id\":1,\"method\":\"mining.update_block\",\"params\":[\"tu8tu5\",%d,\"%s\"]}\n", coinid, blockhash);

	send(sock, buffer, strlen(buffer), 0);
	close(sock);

	//fprintf(stdout, "notify %s\n", buffer);

	return 0;
}



