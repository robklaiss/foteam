from flask import render_template, redirect, url_for, flash, request, session, current_app
from . import bp
from firebase_admin import auth as admin_auth
from firebase_admin.auth import UserNotFoundError
from google.cloud import firestore
import logging
import json

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

@bp.route('/login', methods=['GET', 'POST'])
def login():
    # If user is already logged in, redirect to next URL or index
    if 'user_id' in session:
        next_url = request.args.get('next')
        if next_url:
            return redirect(next_url)
        return redirect(url_for('main.index'))
    
    if request.method == 'POST':
        email = request.form.get('email')
        password = request.form.get('password')
        
        try:
            logger.info(f"Attempting to log in user: {email}")
            # Sign in with Firebase
            auth = current_app.config['auth']
            user = auth.sign_in_with_email_and_password(email, password)
            logger.info(f"Firebase auth successful for user: {email}")
            
            # Store the user's information in the session
            session['user_id'] = user['localId']
            session['email'] = user['email']
            session['token'] = user['idToken']
            
            flash('Logged in successfully.')
            
            # Redirect to next URL if provided
            next_url = request.args.get('next')
            if next_url:
                return redirect(next_url)
            return redirect(url_for('main.index'))
            
        except Exception as e:
            error_message = str(e)
            if 'CONFIGURATION_NOT_FOUND' in error_message:
                logger.error("Firebase Authentication is not properly configured")
                flash('Authentication service is not properly configured. Please contact support.')
            elif 'INVALID_PASSWORD' in error_message:
                flash('Invalid password. Please try again.')
            elif 'EMAIL_NOT_FOUND' in error_message:
                flash('Email not found. Please register first.')
            else:
                logger.error(f"Login failed for user {email}: {error_message}")
                flash('Login failed. Please try again.')
            return render_template('auth/login.html')
    
    return render_template('auth/login.html')

@bp.route('/logout')
def logout():
    session.clear()
    flash('You have been logged out.')
    return redirect(url_for('main.index'))

@bp.route('/register', methods=['GET', 'POST'])
def register():
    # If user is already logged in, redirect to index
    if 'user_id' in session:
        return redirect(url_for('main.index'))
        
    if request.method == 'POST':
        email = request.form.get('email')
        password = request.form.get('password')
        
        if not email or not password:
            flash('Email and password are required')
            return render_template('auth/register.html')
            
        if len(password) < 6:
            flash('Password must be at least 6 characters long')
            return render_template('auth/register.html')
        
        try:
            logger.info(f"Attempting to register user: {email}")
            # Create user with Firebase
            auth = current_app.config['auth']
            
            # Log Firebase configuration for debugging
            logger.info("Current Firebase configuration:")
            safe_config = current_app.config['FIREBASE_CONFIG'].copy()
            safe_config['apiKey'] = '***' if 'apiKey' in safe_config else 'Not set'
            logger.info(json.dumps(safe_config, indent=2))
            
            user = auth.create_user_with_email_and_password(email, password)
            logger.info(f"User created successfully in Firebase: {email}")
            
            # Store user data in Firestore
            user_ref = current_app.config['db'].collection('users').document(user['localId'])
            user_ref.set({
                'email': email,
                'created_at': firestore.SERVER_TIMESTAMP
            })
            logger.info(f"User data stored in Firestore: {email}")
            
            flash('Registration successful. Please log in.')
            return redirect(url_for('auth.login'))
            
        except Exception as e:
            error_message = str(e)
            if 'CONFIGURATION_NOT_FOUND' in error_message:
                logger.error("Firebase Authentication is not properly configured")
                flash('Authentication service is not properly configured. Please enable Email/Password authentication in Firebase Console.')
            elif 'EMAIL_EXISTS' in error_message:
                flash('Email already exists. Please log in or use a different email.')
            elif 'WEAK_PASSWORD' in error_message:
                flash('Password is too weak. Please use at least 6 characters.')
            elif 'INVALID_EMAIL' in error_message:
                flash('Invalid email format. Please use a valid email address.')
            else:
                logger.error(f"Registration failed for user {email}: {error_message}")
                flash('Registration failed. Please try again.')
            return render_template('auth/register.html')
    
    return render_template('auth/register.html')
