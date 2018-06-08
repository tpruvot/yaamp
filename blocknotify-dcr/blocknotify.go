// Copyright (c) 2015-2017 YiiMP

// Sample blocknotify wrapper tool compatible with decred notifications
// will call the standard bin/blocknotify yiimp tool on new block event.

// Note: this tool is connected directly to dcrd, not to the wallet!

package main

import (
	"io/ioutil"
	"log"
	"os/exec"
	"path/filepath"

	"bytes" // dcrd > 0.6+
	"github.com/decred/dcrd/wire"

	"github.com/decred/dcrrpcclient"
//	"github.com/decred/dcrutil"
)

const (
	processName = "blocknotify"    // set the full path if required
	stratumDest = "yaamp.com:3252" // stratum host:port
	coinId = "1574"                // decred database coin id

	dcrdUser = "yiimprpc"
	dcrdPass = "myDcrdPassword"

	debug = false
)

func main() {
	// Only override the handlers for notifications you care about.
	// Also note most of these handlers will only be called if you register
	// for notifications.  See the documentation of the dcrrpcclient
	// NotificationHandlers type for more details about each handler.
	ntfnHandlers := dcrrpcclient.NotificationHandlers{

		OnBlockConnected: func(blockHeader []byte, transactions [][]byte) {
			// log.Printf("Block bytes: %v %v", blockHeader, transactions)
			var bhead wire.BlockHeader
			err := bhead.Deserialize(bytes.NewReader(blockHeader))
			if err == nil {
				str := bhead.BlockHash().String();
				args := []string{ stratumDest, coinId, str }
				out, err := exec.Command(processName, args...).Output()
				if err != nil {
					log.Printf("err %s", err)
				} else if debug {
					log.Printf("out %s", out)
				}
				if (debug) {
					log.Printf("Block connected: %s", str)
				}
			}
		},

	}

	// Connect to local dcrd RPC server using websockets.
	// dcrdHomeDir := dcrutil.AppDataDir("dcrd", false)
	// folder := dcrdHomeDir
	folder := ""
	certs, err := ioutil.ReadFile(filepath.Join(folder, "rpc.cert"))
	if err != nil {
		certs = nil
		log.Printf("%s, trying without TLS...", err)
	}

	connCfg := &dcrrpcclient.ConnConfig{
		Host:         "127.0.0.1:9109",
		Endpoint:     "ws", // websocket

		User:         dcrdUser,
		Pass:         dcrdPass,

		DisableTLS: (certs == nil),
		Certificates: certs,
	}

	client, err := dcrrpcclient.New(connCfg, &ntfnHandlers)
	if err != nil {
		log.Fatalln(err)
	}

	// Register for block connect and disconnect notifications.
	if err := client.NotifyBlocks(); err != nil {
		log.Fatalln(err)
	}
	log.Println("NotifyBlocks: Registration Complete")

	// Wait until the client either shuts down gracefully (or the user
	// terminates the process with Ctrl+C).
	client.WaitForShutdown()
}
