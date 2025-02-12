from app import db

class Marathon(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    name = db.Column(db.String(100), unique=True)
    event_date = db.Column(db.DateTime)
    location = db.Column(db.String(200))
    created_at = db.Column(db.DateTime, default=db.func.now())
    is_active = db.Column(db.Boolean, default=True)

    def __repr__(self):
        return f'<Marathon {self.name}>'
