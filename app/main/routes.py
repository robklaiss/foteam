from flask import Blueprint, render_template, redirect, url_for, flash, request, session, current_app
from . import bp
from app.auth.middleware import login_required
from datetime import datetime
from google.cloud import vision
import uuid
import logging
from google.api_core import exceptions
from firebase_admin import storage, firestore
import os

logger = logging.getLogger(__name__)

def handle_api_error(e, api_name):
    error_msg = str(e)
    if "API has not been used" in error_msg or "disabled" in error_msg:
        flash(f"The {api_name} is not enabled. Please enable it in the Google Cloud Console and try again.", "error")
        logger.error(f"{api_name} is disabled: {error_msg}")
    else:
        flash(f"An error occurred while accessing {api_name}. Please try again later.", "error")
        logger.error(f"Error accessing {api_name}: {error_msg}")

@bp.route('/')
def index():
    if 'user_id' in session:
        return render_template('index.html', user_email=session.get('email'))
    return render_template('index.html')

@bp.route('/manage_marathons')
@login_required
def manage_marathons():
    try:
        # Get marathons from Firestore
        if not current_app.config.get('db'):
            flash("Database connection is not configured. Please check your Firebase settings.", "error")
            return redirect(url_for('main.index'))

        marathons_ref = current_app.config['db'].collection('marathons')
        marathons = [
            {**doc.to_dict(), 'id': doc.id} 
            for doc in marathons_ref.where('user_id', '==', session['user_id']).stream()
        ]
        return render_template('manage_marathons.html', marathons=marathons)
    except exceptions.PermissionDenied as e:
        handle_api_error(e, "Cloud Firestore API")
        return redirect(url_for('main.index'))
    except Exception as e:
        logger.error(f"Error fetching marathons: {str(e)}")
        flash('Error loading marathons. Please try again later.')
        return redirect(url_for('main.index'))

@bp.route('/create_marathon', methods=['POST'])
@login_required
def create_marathon():
    if not current_app.config.get('db'):
        flash("Database connection is not configured. Please check your Firebase settings.", "error")
        return redirect(url_for('main.manage_marathons'))

    name = request.form.get('name')
    event_date = request.form.get('event_date')
    location = request.form.get('location')
    
    if not all([name, event_date, location]):
        flash('All fields are required')
        return redirect(url_for('main.manage_marathons'))
    
    try:
        # Create marathon document in Firestore
        marathon_ref = current_app.config['db'].collection('marathons').document()
        marathon_ref.set({
            'name': name,
            'event_date': datetime.strptime(event_date, '%Y-%m-%d'),
            'location': location,
            'is_active': True,
            'created_at': firestore.SERVER_TIMESTAMP,
            'user_id': session['user_id']
        })
        flash('Marathon created successfully')
    except exceptions.PermissionDenied as e:
        handle_api_error(e, "Cloud Firestore API")
    except Exception as e:
        logger.error(f"Error creating marathon: {str(e)}")
        flash('Error creating marathon: ' + str(e))
    
    return redirect(url_for('main.manage_marathons'))

@bp.route('/update_marathon/<marathon_id>', methods=['POST'])
@login_required
def update_marathon(marathon_id):
    if not current_app.config.get('db'):
        flash("Database connection is not configured. Please check your Firebase settings.", "error")
        return redirect(url_for('main.manage_marathons'))

    try:
        # Get the marathon document
        marathon_ref = current_app.config['db'].collection('marathons').document(marathon_id)
        marathon = marathon_ref.get()

        if not marathon.exists:
            flash('Marathon not found')
            return redirect(url_for('main.manage_marathons'))

        # Verify ownership
        if marathon.to_dict().get('user_id') != session['user_id']:
            flash('You do not have permission to edit this marathon')
            return redirect(url_for('main.manage_marathons'))

        # Update the marathon
        marathon_ref.update({
            'name': request.form.get('name'),
            'event_date': datetime.strptime(request.form.get('event_date'), '%Y-%m-%d'),
            'location': request.form.get('location'),
            'is_active': 'is_active' in request.form,
            'updated_at': firestore.SERVER_TIMESTAMP
        })
        flash('Marathon updated successfully')
    except exceptions.PermissionDenied as e:
        handle_api_error(e, "Cloud Firestore API")
    except Exception as e:
        logger.error(f"Error updating marathon: {str(e)}")
        flash('Error updating marathon: ' + str(e))
    
    return redirect(url_for('main.manage_marathons'))

@bp.route('/upload_photos', methods=['GET', 'POST'])
@login_required
def upload_image():
    if request.method == 'GET':
        try:
            if not current_app.config.get('db'):
                flash("Database connection is not configured. Please check your Firebase settings.", "error")
                return redirect(url_for('main.index'))

            # Get active marathons from Firestore
            marathons_ref = current_app.config['db'].collection('marathons')
            marathons = [
                {**doc.to_dict(), 'id': doc.id} 
                for doc in marathons_ref.where('is_active', '==', True).stream()
            ]
            return render_template('upload.html', marathons=marathons)
        except exceptions.PermissionDenied as e:
            handle_api_error(e, "Cloud Firestore API")
            return redirect(url_for('main.index'))
        except Exception as e:
            logger.error(f"Error loading marathons for upload: {str(e)}")
            flash('Error loading marathons')
            return redirect(url_for('main.index'))

    if 'file' not in request.files:
        return {'error': 'No file part'}, 400
        
    file = request.files['file']
    if file.filename == '':
        return {'error': 'No selected file'}, 400

    if file and allowed_file(file.filename):
        try:
            # Generate unique filename
            filename = str(uuid.uuid4()) + '.' + file.filename.rsplit('.', 1)[1].lower()
            
            # Save file temporarily
            temp_path = os.path.join('/tmp', filename)
            file.save(temp_path)
            
            # Get the storage bucket
            bucket = current_app.config.get('storage')
            if not bucket:
                logger.error("Storage bucket not configured")
                return {'error': 'Storage not configured'}, 500
            
            try:
                # Upload to Firebase Storage
                blob = bucket.blob(f'images/{filename}')
                
                # Upload the file with appropriate content type
                with open(temp_path, 'rb') as temp_file:
                    blob.upload_from_file(
                        temp_file,
                        content_type=file.content_type
                    )
                
                # Clean up temporary file
                os.remove(temp_path)
                
                # Make the blob publicly accessible
                blob.make_public()
                
                # Get the public URL
                image_url = blob.public_url
            except Exception as storage_error:
                logger.error(f"Storage error: {str(storage_error)}")
                if os.path.exists(temp_path):
                    os.remove(temp_path)
                return {'error': f'Storage error: {str(storage_error)}'}, 500
            
            # Create Vision API image
            image = vision.Image()
            image.source.image_uri = image_url
            
            # Perform text detection
            detected_numbers = ''
            try:
                vision_client = vision.ImageAnnotatorClient()
                response = vision_client.text_detection(image=image)
                texts = response.text_annotations
                
                if texts:
                    import re
                    text = texts[0].description
                    # Look for patterns that match runner numbers (typically 1-6 digits)
                    numbers = re.findall(r'\b\d{1,6}\b', text)
                    detected_numbers = ','.join(numbers)
                    logger.info(f"Detected numbers in image {filename}: {detected_numbers}")
            except Exception as vision_error:
                logger.error(f"Vision API error for {filename}: {str(vision_error)}")
                logger.exception("Full traceback for Vision API error:")
            
            # Store image data in Firestore
            image_data = {
                'filename': filename,
                'url': image_url,
                'marathon_id': request.form.get('marathon_id'),
                'detected_numbers': detected_numbers,
                'upload_time': firestore.SERVER_TIMESTAMP,
                'user_id': session['user_id']
            }
            
            try:
                image_ref = current_app.config['db'].collection('images').document()
                image_ref.set(image_data)
                logger.info(f"Successfully stored image data in Firestore for {filename}")
            except Exception as db_error:
                logger.error(f"Firestore error for {filename}: {str(db_error)}")
                logger.exception("Full traceback for Firestore error:")
                return {'error': 'Failed to store image data'}, 500
            
            return {'message': 'File uploaded successfully', 'image_id': image_ref.id}, 200
        except exceptions.PermissionDenied as e:
            handle_api_error(e, "Google Cloud API")
            return {'error': str(e)}, 500
        except Exception as e:
            logger.error(f"Error uploading file: {str(e)}")
            if os.path.exists(temp_path):
                os.remove(temp_path)
            return {'error': str(e)}, 500

    return {'error': 'Invalid file type'}, 400

@bp.route('/gallery')
@login_required
def gallery():
    try:
        if not current_app.config.get('db'):
            flash("Database connection is not configured. Please check your Firebase settings.", "error")
            return redirect(url_for('main.index'))

        marathon_id = request.args.get('marathon_id')
        search_numbers = request.args.get('search_numbers', '')
        page = request.args.get('page', 1, type=int)
        
        # Query images from Firestore
        images_ref = current_app.config['db'].collection('images')
        query = images_ref
        
        if marathon_id:
            query = query.where('marathon_id', '==', marathon_id)
        
        # Get all images that match the criteria
        images = []
        try:
            docs = query.order_by('upload_time', direction=firestore.Query.DESCENDING).stream()
            for doc in docs:
                data = doc.to_dict()
                data['id'] = doc.id  # Add document ID to the data
                images.append(data)
        except Exception as e:
            logger.error(f"Error fetching images: {str(e)}")
            logger.exception("Full traceback for image fetch error:")
        
        # Filter by numbers if search_numbers is provided
        if search_numbers:
            numbers = [n.strip() for n in search_numbers.split(',')]
            images = [
                img for img in images 
                if img.get('detected_numbers') and 
                any(number in img['detected_numbers'] for number in numbers)
            ]
        
        # Simple pagination
        per_page = 24
        total = len(images)
        start_idx = (page - 1) * per_page
        end_idx = start_idx + per_page
        images_page = images[start_idx:end_idx]
        
        # Get marathons for filter dropdown
        marathons = []
        try:
            marathons_ref = current_app.config['db'].collection('marathons')
            marathon_docs = marathons_ref.where('is_active', '==', True).stream()
            marathons = [doc.to_dict() for doc in marathon_docs]
        except Exception as e:
            logger.error(f"Error fetching marathons: {str(e)}")
            logger.exception("Full traceback for marathon fetch error:")
        
        return render_template('gallery.html',
                            images=images_page,
                            marathons=marathons,
                            selected_marathon=marathon_id,
                            search_numbers=search_numbers,
                            page=page,
                            total_pages=(total + per_page - 1) // per_page)
    except exceptions.PermissionDenied as e:
        handle_api_error(e, "Cloud Firestore API")
        return redirect(url_for('main.index'))
    except Exception as e:
        logger.error(f"Error loading gallery: {str(e)}")
        logger.exception("Full traceback for gallery error:")
        flash('Error loading gallery')
        return redirect(url_for('main.index'))

def allowed_file(filename):
    return '.' in filename and filename.rsplit('.', 1)[1].lower() in {'png', 'jpg', 'jpeg', 'gif'}
