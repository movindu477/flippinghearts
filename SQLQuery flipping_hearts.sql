use flipping_hearts;


CREATE TABLE Users (
    user_id INT IDENTITY(1,1) PRIMARY KEY,
    username NVARCHAR(50) NOT NULL UNIQUE,
    password NVARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL,
    last_login DATETIME NULL
);

ALTER TABLE Users ADD score INT DEFAULT 0;
ALTER TABLE Users ADD level INT DEFAULT 1;

select * from Users;