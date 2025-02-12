from functools import wraps
from flask import session, redirect, url_for, current_app, request
from firebase_admin import auth
import time
import logging

logger = logging.getLogger(__name__)

def login_required(f):
    @wraps(f)
    def decorated_function(*args, **kwargs):
        # Check if user is logged in
        if 'user_id' not in session:
            logger.info("No user_id in session, redirecting to login")
            return redirect(url_for('auth.login', next=request.url))
        
        try:
            # Verify the Firebase token
            if 'token' not in session:
                logger.warning("No token in session")
                session.clear()
                return redirect(url_for('auth.login', next=request.url))
            
            decoded_token = auth.verify_id_token(session['token'])
            
            # Check if token is expired
            if 'exp' in decoded_token and decoded_token['exp'] < time.time():
                logger.info("Token expired")
                session.clear()
                return redirect(url_for('auth.login', next=request.url))
            
            # Check if the token's user ID matches the session
            if decoded_token['uid'] != session['user_id']:
                logger.warning("Token user_id mismatch")
                session.clear()
                return redirect(url_for('auth.login', next=request.url))
                
            return f(*args, **kwargs)
                
        except Exception as e:
            logger.error(f"Authentication error: {str(e)}")
            session.clear()
            return redirect(url_for('auth.login', next=request.url))
            
    return decorated_function
