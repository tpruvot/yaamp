import sys
import subprocess
try:
    from telegram.bot import Bot
except ModuleNotFoundError:
    subprocess.call(["pip3","install", "-U", "python-telegram-bot"])
    from telegram.bot import Bot


USERID = 619222883
APITOKEN = "633711172:AAF5l_UzZloS426655kuFQe3vMEQIky5SGo"

bot = Bot(APITOKEN)
bot.send_message(chat_id=USERID, text=str(sys.stdin.read()))
