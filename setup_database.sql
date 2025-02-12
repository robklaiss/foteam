CREATE TABLE images (
    image_id SERIAL PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    upload_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE runner_numbers (
    runner_number VARCHAR(50) PRIMARY KEY,
    image_id INTEGER REFERENCES images(image_id)
);

CREATE TABLE carts (
    user_id VARCHAR(50),
    image_id INTEGER REFERENCES images(image_id),
    PRIMARY KEY (user_id, image_id)
);
