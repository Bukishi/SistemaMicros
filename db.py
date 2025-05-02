import psycopg2
import json

with open("db_config.json") as f:
    conf = json.load(f)

class Config:
    DB_URI = f"postgresql://{conf['user']}:{conf['pass']}@{conf['host']}:{conf['port']}/{conf['dbname']}"


def get_connection():
    return psycopg2.connect(Config.DB_URI)
