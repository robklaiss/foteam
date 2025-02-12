from flask import Flask, redirect, url_for
from config import Config
import firebase_admin
from firebase_admin import credentials, firestore, storage
import pyrebase
import logging
import os

# Set up logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

def create_app(config_class=Config):
    app = Flask(__name__, 
                template_folder=os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'templates'))
    app.config.from_object(config_class)

    try:
        # Initialize Firebase Admin SDK if not already initialized
        if not firebase_admin._apps:
            if not os.path.exists(app.config['FIREBASE_ADMIN_CREDENTIALS']):
                logger.error(f"Firebase credentials file not found at {app.config['FIREBASE_ADMIN_CREDENTIALS']}")
                app.config['firebase'] = None
                app.config['auth'] = None
                app.config['db'] = None
                return app
            
            cred = credentials.Certificate(app.config['FIREBASE_ADMIN_CREDENTIALS'])
            # Initialize with storage bucket
            firebase_admin.initialize_app(cred, {
                'storageBucket': os.environ.get('FIREBASE_STORAGE_BUCKET')
            })
            logger.info("Firebase Admin SDK initialized successfully")
        
        # Initialize Firestore
        db = firestore.client()
        logger.info("Firestore initialized successfully")
        
        # Initialize Firebase Storage bucket
        bucket = storage.bucket(os.environ.get('FIREBASE_STORAGE_BUCKET'))
        logger.info("Firebase Storage initialized successfully")
        
        # Initialize Firebase for client-side operations
        if not all(app.config['FIREBASE_CONFIG'].values()):
            missing_keys = [k for k, v in app.config['FIREBASE_CONFIG'].items() if not v]
            logger.error(f"Missing Firebase configuration keys: {missing_keys}")
            app.config['firebase'] = None
            app.config['auth'] = None
            app.config['db'] = None
            app.config['storage'] = None
            return app
        
        firebase = pyrebase.initialize_app(app.config['FIREBASE_CONFIG'])
        auth = firebase.auth()
        logger.info("Firebase Authentication initialized successfully")
        
        # Make Firebase services available to the app
        app.config['firebase'] = firebase
        app.config['auth'] = auth
        app.config['db'] = db
        app.config['storage'] = bucket

    except Exception as e:
        logger.error(f"Error initializing Firebase: {str(e)}")
        # Still create the app but log the error
        app.config['firebase'] = None
        app.config['auth'] = None
        app.config['db'] = None
        app.config['storage'] = None

    # Register blueprints
    from app.main import bp as main_bp
    app.register_blueprint(main_bp)

    from app.auth import bp as auth_bp
    app.register_blueprint(auth_bp)

    @app.route('/')
    def index():
        return redirect(url_for('main.index'))

    return app
