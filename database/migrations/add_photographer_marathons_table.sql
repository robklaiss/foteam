-- Migration to add photographer_marathons table
CREATE TABLE IF NOT EXISTS photographer_marathons (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    marathon_id INTEGER NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (marathon_id) REFERENCES marathons(id) ON DELETE CASCADE,
    UNIQUE(user_id, marathon_id)
);
