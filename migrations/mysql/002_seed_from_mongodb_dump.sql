-- 002_seed_from_mongodb_dump.sql
-- Seeded into SQL-native tables (no Mongo-specific columns).
-- Source dumps:
--   /Users/jack/Desktop/db-dump/users.json
--   /Users/jack/Desktop/db-dump/notifications.json
--   /Users/jack/Desktop/db-dump/surprises.json

START TRANSACTION;

INSERT INTO users (id, name, password, created_at, updated_at) VALUES
(1, 'admin', '$2b$06$c7Rwtc0qWrI4iMv2nXrYOu9VOaqsIpkPKZbnSPtYuH2bSyv.V9Gne', '2023-09-22 05:24:16', '2023-09-22 05:24:16')
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  password = VALUES(password),
  created_at = VALUES(created_at),
  updated_at = VALUES(updated_at);

INSERT INTO notifications (id, reveal_date, executed_date, created_at, updated_at) VALUES
(1, '2023-10-31 19:40:00', NULL, '2023-10-31 19:39:07', '2023-10-31 19:39:07'),
(2, '2023-10-31 19:41:00', NULL, '2023-10-31 19:39:58', '2023-10-31 19:39:58'),
(3, '2023-10-31 19:43:00', NULL, '2023-10-31 19:42:17', '2023-10-31 19:42:17'),
(4, '2023-10-31 19:45:00', NULL, '2023-10-31 19:44:32', '2023-10-31 19:44:32'),
(5, '2023-10-31 19:47:00', NULL, '2023-10-31 19:46:52', '2023-10-31 19:46:52'),
(6, '2023-10-31 19:50:00', NULL, '2023-10-31 19:49:03', '2023-10-31 19:49:03'),
(7, '2023-09-12 02:00:00', '2023-09-12 02:00:00', '2023-10-31 19:50:56', '2023-10-31 19:50:56'),
(8, '2023-09-27 21:23:00', '2023-09-27 21:23:00', '2023-10-31 19:50:56', '2023-10-31 19:50:56'),
(9, '2023-09-29 16:00:00', '2023-09-29 16:00:00', '2023-10-31 19:50:56', '2023-10-31 19:50:56'),
(10, '2023-11-04 00:00:00', NULL, '2023-10-31 19:50:56', '2023-10-31 19:50:56'),
(11, '2023-11-12 02:00:00', NULL, '2023-10-31 19:50:56', '2023-10-31 19:50:56'),
(12, '2023-11-11 23:30:00', NULL, '2023-10-31 19:50:57', '2023-10-31 19:50:57'),
(13, '2023-11-04 11:00:00', NULL, '2023-10-31 19:50:57', '2023-10-31 19:50:57'),
(14, '2023-10-06 14:42:00', '2023-10-06 14:42:00', '2023-10-31 19:50:57', '2023-10-31 19:50:57'),
(15, '2023-11-01 00:00:00', NULL, '2023-10-31 19:50:57', '2023-10-31 19:50:57')
ON DUPLICATE KEY UPDATE
  reveal_date = VALUES(reveal_date),
  executed_date = VALUES(executed_date),
  created_at = VALUES(created_at),
  updated_at = VALUES(updated_at);

INSERT INTO surprises (id, notification_id, title, description, surprise_number, magnitude, variety, icon_class, viewed, live, completed_at, reveal_date, created_at, updated_at) VALUES
(1, 7, 'Rose-bath', 'Candles, wine, and roses...', NULL, 'small', 'romantic', 'bi bi-flower1', 1, 1, '2023-09-24 20:45:47', '2023-09-12 02:00:00', '2023-09-24 20:43:53', '2023-10-31 19:50:56'),
(2, 8, 'New Website', 'You get hints as to what your upcoming surprises will be. Can you guess them all?', NULL, 'small', 'cute', 'fa-solid fa-globe', 1, 1, '2023-10-31 17:01:40', '2023-09-27 21:23:00', '2023-09-24 20:57:46', '2023-10-31 19:50:56'),
(3, 9, 'New Haircut', 'You get to choose ANY new haircut for your man. Make it good...', NULL, 'small', 'overdue', 'bi bi-scissors', 1, 1, '2024-01-14 21:46:17', '2023-09-29 16:00:00', '2023-09-24 21:04:01', '2024-01-14 21:46:17'),
(4, 10, 'Alone Time #1', 'You get a whole day alone. Free to do what you''d like.', NULL, 'small', 'sweet', 'bi bi-sign-do-not-enter', 1, 1, '2024-03-05 03:18:57', '2023-11-11 01:00:00', '2023-09-28 13:54:04', '2024-03-05 03:18:57'),
(5, 11, 'Candlelit Dinner', 'Steak, Lobster, Mashed potatoes, wine...', NULL, 'small', 'romantic', 'fa-solid fa-utensils', 0, 0, NULL, '2024-01-14 12:00:00', '2023-09-28 19:10:26', '2024-01-10 23:39:54'),
(6, 12, 'Wine Tasting', 'There''s a cute little spot I found ;)', NULL, 'small', 'cute', 'fa-solid fa-wine-glass', 0, 0, NULL, '2023-11-11 23:30:00', '2023-09-28 19:21:32', '2023-11-18 12:23:03'),
(7, 13, 'Day-trip', 'We are going to go on a mini road trip to see the autumn leaves.', NULL, 'small', 'sweet', 'fa-brands fa-canadian-maple-leaf', 0, 0, NULL, '2024-10-19 11:00:00', '2023-09-29 13:06:29', '2024-08-14 01:52:22'),
(8, 14, '$100', 'Spend it on whatever you''d like. I got a promo on TEMU ;)', NULL, 'small', 'sweet', 'fa-solid fa-money-bill-1-wave', 1, 1, NULL, '2023-10-06 14:42:00', '2023-10-06 14:41:06', '2023-10-31 19:50:57'),
(9, 15, 'Bridgerton Boardgame', 'Nuff said...', NULL, 'small', 'mystery', 'fa-solid fa-puzzle-piece', 1, 1, '2023-11-01 21:39:49', '2023-11-01 00:00:00', '2023-10-27 22:13:21', '2023-11-01 21:39:49'),
(10, NULL, 'Spa Day', 'Your choice of anything up to $250 at the Curtain Bluff Spa. (I don''t have to be there)', NULL, 'small', 'sweet', 'fa-solid fa-spa', 1, 1, '2024-01-14 21:46:28', '2023-12-09 17:00:00', '2023-12-01 17:51:25', '2024-01-14 21:46:28'),
(11, NULL, 'Awake & Prejudice', 'We watch that film, and I stay up the whole time.', NULL, 'small', 'sweet', 'bi bi-film', 1, 1, NULL, '2024-01-12 01:00:00', '2024-01-05 18:42:53', '2024-01-16 23:01:14'),
(12, NULL, 'Alone Time #2', 'You get a whole day alone. Free to do what you''d like.', NULL, 'small', 'sweet', 'bi bi-sign-do-not-enter', 1, 1, NULL, '2024-01-20 01:00:00', '2024-01-05 18:46:56', '2024-08-23 19:44:37'),
(13, NULL, 'Rose-bath AGAIN', 'Candles, wine, and roses...', NULL, 'small', 'romantic', 'bi bi-flower1', 1, 1, NULL, '2024-01-10 00:00:00', '2024-01-05 18:50:18', '2024-01-10 00:15:35'),
(14, NULL, 'Treasure Hunt-ish', 'When you''re ready, I''ll issue you your first clue ;) (It takes a minute to set it up)', NULL, 'small', 'mystery', 'bi bi-compass', 1, 1, '2025-02-24 09:19:03', '2024-09-28 11:00:00', '2024-01-05 18:55:47', '2025-02-24 09:19:03'),
(15, NULL, 'Murder Mystery', 'You & me solving clues against the world.', NULL, 'small', 'special', 'fa-solid fa-user-secret', 0, 0, NULL, '2024-09-24 12:00:00', '2024-03-05 02:42:21', '2024-08-14 01:30:54'),
(16, NULL, 'Snuggle Time', 'You don''t have to take my hoodies any more now that you get your very own snuggie!', NULL, 'small', 'cute', 'fa-solid fa-shirt', 1, 1, '2025-02-22 18:57:46', '2025-01-25 13:00:00', '2024-08-14 01:25:20', '2025-02-22 18:57:46'),
(17, NULL, 'Clean Everything', 'Everything will be cleaned by the time you return.', NULL, 'small', 'sweet', 'fa-solid fa-broom', 1, 1, '2025-02-19 14:42:20', '2024-08-23 16:00:00', '2024-08-23 19:43:22', '2025-02-19 14:42:20'),
(18, NULL, '1 Hour Calming Massage', 'Time to relax........', NULL, 'small', 'sweet', 'bi bi-flower1', 1, 1, '2025-02-19 14:42:24', '2024-09-12 16:00:00', '2024-09-12 19:11:01', '2025-02-19 14:42:24'),
(19, NULL, '$50 Starbucks Giftcard', 'Java good time!', NULL, 'small', 'sweet', 'fa-solid fa-mug-hot', 1, 1, NULL, '2025-02-23 13:00:00', '2025-02-19 14:28:17', '2025-02-24 11:53:59'),
(20, NULL, 'Bath Bomb', 'Boom!', NULL, 'small', 'sweet', 'fa-solid fa-bomb', 1, 1, NULL, '2025-03-10 23:00:00', '2025-03-05 23:53:21', '2025-03-11 01:14:36'),
(21, NULL, 'Flowers Delivery', 'Flowers will be delivered to you today (07/20/2025). Just Because...', NULL, 'small', 'sweet', 'bi bi-flower1', 1, 1, NULL, '2025-07-20 16:00:00', '2025-07-20 19:27:30', '2025-07-20 19:38:12')
ON DUPLICATE KEY UPDATE
  notification_id = VALUES(notification_id),
  title = VALUES(title),
  description = VALUES(description),
  surprise_number = VALUES(surprise_number),
  magnitude = VALUES(magnitude),
  variety = VALUES(variety),
  icon_class = VALUES(icon_class),
  viewed = VALUES(viewed),
  live = VALUES(live),
  completed_at = VALUES(completed_at),
  reveal_date = VALUES(reveal_date),
  created_at = VALUES(created_at),
  updated_at = VALUES(updated_at);

ALTER TABLE users AUTO_INCREMENT = 2;
ALTER TABLE notifications AUTO_INCREMENT = 16;
ALTER TABLE surprises AUTO_INCREMENT = 22;

COMMIT;

