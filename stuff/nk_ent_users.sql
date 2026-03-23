-- Create users table for nk_ent database
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(15) NOT NULL,
  `firstname` varchar(255) NOT NULL,
  `lastname` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','manager','cashier') NOT NULL DEFAULT 'cashier',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add indexes
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

-- Set AUTO_INCREMENT
ALTER TABLE `users`
  MODIFY `id` int(15) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

-- Insert admin user
INSERT INTO `users` (`id`, `firstname`, `lastname`, `email`, `password`, `role`) VALUES
(1, 'Admin', 'User', 'admin@hardware.com', '$2y$10$9w3WnsaRH2O9V8mWFmuf2.FlefUpKF6mXMCVRrUTFOhPrVryJMJhu', 'admin');
