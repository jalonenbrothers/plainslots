-- 001_create_polls.sql — PlainSlots core schema.
-- Creates: polls, slots, participants, votes. Safe to re-run (IF NOT EXISTS everywhere).
-- Requires: nothing. Required by: the application (all endpoints).

CREATE TABLE IF NOT EXISTS polls (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  slug CHAR(10) NOT NULL UNIQUE,        -- base62 random, opaque, public
  edit_slug CHAR(16) NOT NULL UNIQUE,   -- base62 random, opaque, secret admin key
  title VARCHAR(200) NOT NULL,
  description VARCHAR(1000) NULL,
  status ENUM('open','closed') NOT NULL DEFAULT 'open',
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS slots (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  poll_id BIGINT NOT NULL,
  position INT NOT NULL,
  label VARCHAR(80) NOT NULL,
  UNIQUE KEY uq_poll_pos (poll_id, position),
  KEY k_poll (poll_id),
  CONSTRAINT fk_slots_poll FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS participants (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  poll_id BIGINT NOT NULL,
  name VARCHAR(50) NOT NULL,
  created_at DATETIME NOT NULL,
  KEY k_poll (poll_id),
  CONSTRAINT fk_participants_poll FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS votes (
  participant_id BIGINT NOT NULL,
  slot_id BIGINT NOT NULL,
  value ENUM('yes','maybe','no') NOT NULL,
  PRIMARY KEY (participant_id, slot_id),
  CONSTRAINT fk_votes_participant FOREIGN KEY (participant_id) REFERENCES participants(id) ON DELETE CASCADE,
  CONSTRAINT fk_votes_slot FOREIGN KEY (slot_id) REFERENCES slots(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;