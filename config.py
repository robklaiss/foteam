import os
from dotenv import load_dotenv

basedir = os.path.abspath(os.path.dirname(__file__))
load_dotenv(os.path.join(basedir, '.env'))

class Config:
    SECRET_KEY = os.environ.get('SECRET_KEY') or 'dev-key-please-change'
    
    # Firebase Configuration
    FIREBASE_CONFIG = {
        'apiKey': os.environ.get('FIREBASE_API_KEY'),
        'authDomain': 'foteam-py.firebaseapp.com',
        'projectId': 'foteam-py',
        'storageBucket': os.environ.get('FIREBASE_STORAGE_BUCKET'),
        'messagingSenderId': os.environ.get('FIREBASE_MESSAGING_SENDER_ID'),
        'appId': os.environ.get('FIREBASE_APP_ID'),
        'databaseURL': 'https://foteam-py-default-rtdb.firebaseio.com'
    }
    
    FIREBASE_ADMIN_CREDENTIALS = os.environ.get('FIREBASE_ADMIN_CREDENTIALS')