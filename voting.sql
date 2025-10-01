-- Create DB
CREATE DATABASE IF NOT EXISTS voting_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE voting_system;

-- Positions
CREATE TABLE IF NOT EXISTS positions (
  posID INT AUTO_INCREMENT PRIMARY KEY,
  posName VARCHAR(100) NOT NULL,
  numOfPositions INT NOT NULL DEFAULT 1,
  posStatus ENUM('active','inactive') NOT NULL DEFAULT 'active'
);

-- Candidates
CREATE TABLE IF NOT EXISTS candidates (
  candID INT AUTO_INCREMENT PRIMARY KEY,
  candFName VARCHAR(100) NOT NULL,
  candMName VARCHAR(100),
  candLName VARCHAR(100) NOT NULL,
  posID INT NOT NULL,
  candStat ENUM('active','inactive') NOT NULL DEFAULT 'active',
  FOREIGN KEY (posID) REFERENCES positions(posID) ON DELETE CASCADE
);

-- Voters
CREATE TABLE IF NOT EXISTS voters (
  voterID INT AUTO_INCREMENT PRIMARY KEY,
  voterPass VARCHAR(255) NOT NULL,           -- hashed password
  voterFName VARCHAR(100) NOT NULL,
  voterMName VARCHAR(100),
  voterLName VARCHAR(100) NOT NULL,
  voterStat ENUM('active','inactive') NOT NULL DEFAULT 'active',
  voted TINYINT(1) NOT NULL DEFAULT 0        -- 0 = not voted, 1 = voted
);

-- Votes (each row = voter voted for a candidate in a position)
CREATE TABLE IF NOT EXISTS votes (
  voteID INT AUTO_INCREMENT PRIMARY KEY,
  posID INT NOT NULL,
  voterID INT NOT NULL,
  candID INT NOT NULL,
  vote_date DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (posID) REFERENCES positions(posID) ON DELETE CASCADE,
  FOREIGN KEY (voterID) REFERENCES voters(voterID) ON DELETE CASCADE,
  FOREIGN KEY (candID) REFERENCES candidates(candID) ON DELETE CASCADE
);
