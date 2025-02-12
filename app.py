from flask import Flask, request, jsonify, render_template, redirect, url_for, flash
from config import Config
from flask_sqlalchemy import SQLAlchemy
from flask_login import LoginManager, login_required, current_user, login_user, logout_user
from google.cloud import storage, vision
from datetime import datetime
from werkzeug.utils import secure_filename
import os

# Initialize extensions
db = SQLAlchemy()
login = LoginManager()
login.login_view = 'auth.login'

def create_app(config_class=Config):
    app = Flask(__name__)
    app.config.from_object(config_class)

    # Initialize extensions
    db.init_app(app)
    login.init_app(app)

    # Initialize GCS client
    if os.path.exists(app.config['GCS_CREDENTIALS_JSON']):
        gcs_client = storage.Client.from_service_account_json(app.config['GCS_CREDENTIALS_JSON'])
    else:
        gcs_client = storage.Client()

    # Initialize Vision client
    vision_client = vision.ImageAnnotatorClient()

    # Get or create bucket
    gcs_bucket = gcs_client.bucket(app.config['GCS_BUCKET_NAME'])
    if not gcs_bucket.exists():
        gcs_bucket.create()

    # Database Models
    class Image(db.Model):
        __tablename__ = 'images'
        id = db.Column(db.Integer, primary_key=True)
        filename = db.Column(db.String(255), nullable=False)
        upload_time = db.Column(db.DateTime, default=datetime.utcnow)
        marathon_id = db.Column(db.Integer, db.ForeignKey('marathons.id'), nullable=True)
        detected_numbers = db.Column(db.String(255), nullable=True)

    class Marathon(db.Model):
        __tablename__ = 'marathons'
        id = db.Column(db.Integer, primary_key=True)
        name = db.Column(db.String(255), nullable=False)
        event_date = db.Column(db.DateTime, nullable=False)
        location = db.Column(db.String(255), nullable=False)
        is_active = db.Column(db.Boolean, default=True)

    class RunnerNumber(db.Model):
        __tablename__ = 'runner_numbers'
        number = db.Column(db.String(50), primary_key=True)
        image_id = db.Column(db.Integer, db.ForeignKey('images.id'), nullable=False)

    class Cart(db.Model):
        __tablename__ = 'carts'
        user_id = db.Column(db.String(50), primary_key=True)
        image_id = db.Column(db.Integer, db.ForeignKey('images.id'), primary_key=True)

    class User(db.Model):
        __tablename__ = 'users'
        id = db.Column(db.Integer, primary_key=True)
        email = db.Column(db.String(255), nullable=False, unique=True)
        password = db.Column(db.String(255), nullable=False)
        is_active = db.Column(db.Boolean, default=False)

        def check_password(self, password):
            return self.password == password

        def set_password(self, password):
            self.password = password

    # Configuration
    ALLOWED_EXTENSIONS = {'png', 'jpg', 'jpeg', 'gif'}

    def allowed_file(filename):
        return '.' in filename and \
               filename.rsplit('.', 1)[1].lower() in ALLOWED_EXTENSIONS

    class LoginForm(FlaskForm):
        email = StringField('Email', validators=[DataRequired()])
        password = PasswordField('Password', validators=[DataRequired()])
        remember = BooleanField('Remember Me')
        submit = SubmitField('Login')

    @app.cli.command()
    def init_db():
        with app.app_context():
            db.create_all()
        print('Database tables created successfully')

    @app.template_filter('time_ago')
    def time_ago_filter(dt):
        now = datetime.now(datetime.utcnow().astimezone().tzinfo)
        diff = now - dt
        periods = (
            (diff.days // 365, 'year', 'years'),
            (diff.days // 30, 'month', 'months'),
            (diff.days // 7, 'week', 'weeks'),
            (diff.days, 'day', 'days'),
            (diff.seconds // 3600, 'hour', 'hours'),
            (diff.seconds // 60, 'minute', 'minutes'),
            (diff.seconds, 'second', 'seconds'),
        )
        for period, singular, plural in periods:
            if period >= 1:
                return f"{period} {singular if period == 1 else plural} ago"
        return "just now"

    @app.route('/')
    def index():
        return render_template('index.html')

    @app.route('/manage_marathons')
    @login_required
    def manage_marathons():
        marathons = Marathon.query.all()
        return render_template('manage_marathons.html', marathons=marathons)

    @app.route('/create_marathon', methods=['POST'])
    @login_required
    def create_marathon():
        name = request.form.get('name')
        event_date = request.form.get('event_date')
        location = request.form.get('location')
        
        if not all([name, event_date, location]):
            flash('All fields are required')
            return redirect(url_for('manage_marathons'))
        
        try:
            marathon = Marathon(
                name=name,
                event_date=datetime.strptime(event_date, '%Y-%m-%d'),
                location=location,
                is_active=True
            )
            db.session.add(marathon)
            db.session.commit()
            flash('Marathon created successfully')
        except Exception as e:
            flash('Error creating marathon: ' + str(e))
        
        return redirect(url_for('manage_marathons'))

    @app.route('/upload', methods=['GET', 'POST'])
    @login_required
    def upload_image():
        if request.method == 'GET':
            marathons = Marathon.query.filter_by(is_active=True).all()
            return render_template('upload.html', marathons=marathons)

        if 'file' not in request.files:
            return jsonify({'error': 'No file part'}), 400
            
        file = request.files['file']
        if file.filename == '':
            return jsonify({'error': 'No selected file'}), 400

        if file and allowed_file(file.filename):
            filename = secure_filename(file.filename)
            blob = gcs_bucket.blob(filename)
            blob.upload_from_file(file)
            
            # Create Vision API image
            image = vision.Image()
            image.source.image_uri = f"gs://{app.config['GCS_BUCKET_NAME']}/{filename}"
            
            # Perform text detection
            response = vision_client.text_detection(image=image)
            texts = response.text_annotations
            
            detected_numbers = []
            if texts:
                # Extract numbers from detected text
                import re
                text = texts[0].description
                numbers = re.findall(r'\d+', text)
                detected_numbers = ','.join(numbers)
            
            new_image = Image(
                filename=filename,
                marathon_id=request.form.get('marathon_id'),
                detected_numbers=detected_numbers
            )
            db.session.add(new_image)
            db.session.commit()
            
            return jsonify({
                'message': 'File uploaded successfully',
                'image_id': new_image.id
            }), 200

        return jsonify({'error': 'Invalid file type'}), 400

    @app.route('/gallery')
    @login_required
    def gallery():
        marathon_id = request.args.get('marathon_id')
        search_numbers = request.args.get('search_numbers', '')
        page = request.args.get('page', 1, type=int)
        
        query = Image.query
        
        if marathon_id:
            query = query.filter_by(marathon_id=marathon_id)
        if search_numbers:
            numbers = [n.strip() for n in search_numbers.split(',')]
            from sqlalchemy import or_
            query = query.filter(or_(*[
                Image.detected_numbers.ilike(f'%{number}%') 
                for number in numbers
            ]))
        
        images = query.order_by(Image.upload_time.desc()).paginate(
            page=page, per_page=24, error_out=False)
        
        marathons = Marathon.query.filter_by(is_active=True).all()
        return render_template('gallery.html',
                             images=images,
                             marathons=marathons,
                             selected_marathon=marathon_id,
                             search_numbers=search_numbers)

    @app.route('/login', methods=['GET', 'POST'])
    def login():
        if current_user.is_authenticated:
            return redirect(url_for('manage_marathons'))
        
        form = LoginForm()
        if form.validate_on_submit():
            user = User.query.filter_by(email=form.email.data).first()
            if user and user.check_password(form.password.data):
                login_user(user, remember=form.remember.data)
                next_page = request.args.get('next')
                return redirect(next_page or url_for('manage_marathons'))
            flash('Invalid email or password')
        return render_template('login.html', form=form)

    @app.route('/logout')
    def logout():
        logout_user()
        return redirect(url_for('login'))

    @app.route('/dashboard')
    @login_required
    def dashboard():
        return 'User Dashboard'

    @app.route('/create-test-user')
    def create_test_user():
        if not User.query.filter_by(email='test@example.com').first():
            test_user = User(
                email='test@example.com',
                is_active=True
            )
            test_user.set_password('testpassword')
            db.session.add(test_user)
            db.session.commit()
        return redirect(url_for('login'))

    return app

from app.models import User

@login.user_loader
def load_user(user_id):
    return User.query.get(int(user_id))

if __name__ == '__main__':
    app = create_app()
    app.run(debug=True)
