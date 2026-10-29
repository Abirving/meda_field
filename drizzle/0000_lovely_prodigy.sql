CREATE TABLE `audits` (
	`id` text PRIMARY KEY NOT NULL,
	`space` text NOT NULL,
	`actor` text NOT NULL,
	`action` text NOT NULL,
	`detail` text NOT NULL,
	`created_at` integer NOT NULL
);
--> statement-breakpoint
CREATE TABLE `bookings` (
	`id` text PRIMARY KEY NOT NULL,
	`space` text NOT NULL,
	`user_id` text NOT NULL,
	`field_id` text NOT NULL,
	`date` text NOT NULL,
	`hour` integer NOT NULL,
	`amount` integer NOT NULL,
	`commission` integer NOT NULL,
	`status` text NOT NULL,
	`payment_status` text NOT NULL,
	`payment_method` text NOT NULL,
	`team` text NOT NULL,
	`reference` text NOT NULL,
	`token` text NOT NULL,
	`idempotency_key` text NOT NULL,
	`created_at` integer NOT NULL,
	`checked_at` integer,
	FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON UPDATE no action ON DELETE no action,
	FOREIGN KEY (`field_id`) REFERENCES `fields`(`id`) ON UPDATE no action ON DELETE no action
);
--> statement-breakpoint
CREATE UNIQUE INDEX `bookings_reference_unique` ON `bookings` (`reference`);--> statement-breakpoint
CREATE UNIQUE INDEX `bookings_token_unique` ON `bookings` (`token`);--> statement-breakpoint
CREATE UNIQUE INDEX `booking_idempotency` ON `bookings` (`space`,`user_id`,`idempotency_key`);--> statement-breakpoint
CREATE INDEX `booking_field_date` ON `bookings` (`field_id`,`date`);--> statement-breakpoint
CREATE TABLE `challenges` (
	`id` text PRIMARY KEY NOT NULL,
	`space` text NOT NULL,
	`phone` text NOT NULL,
	`code_hash` text NOT NULL,
	`attempts` integer DEFAULT 0 NOT NULL,
	`used` integer DEFAULT 0 NOT NULL,
	`expires_at` integer NOT NULL,
	`created_at` integer NOT NULL
);
--> statement-breakpoint
CREATE INDEX `challenge_rate_limit` ON `challenges` (`space`,`phone`,`created_at`);--> statement-breakpoint
CREATE TABLE `disputes` (
	`id` text PRIMARY KEY NOT NULL,
	`space` text NOT NULL,
	`booking_id` text NOT NULL,
	`user_id` text NOT NULL,
	`reason` text NOT NULL,
	`status` text DEFAULT 'open' NOT NULL,
	`resolution` text,
	`created_at` integer NOT NULL,
	`resolved_at` integer,
	FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON UPDATE no action ON DELETE no action
);
--> statement-breakpoint
CREATE UNIQUE INDEX `one_dispute_per_booking` ON `disputes` (`booking_id`);--> statement-breakpoint
CREATE TABLE `fields` (
	`id` text PRIMARY KEY NOT NULL,
	`space` text NOT NULL,
	`owner_id` text NOT NULL,
	`name` text NOT NULL,
	`subcity` text NOT NULL,
	`address` text NOT NULL,
	`description` text NOT NULL,
	`format` text NOT NULL,
	`price` integer NOT NULL,
	`peak_price` integer NOT NULL,
	`peak_start` integer DEFAULT 17 NOT NULL,
	`opens` integer DEFAULT 6 NOT NULL,
	`closes` integer DEFAULT 23 NOT NULL,
	`amenities` text NOT NULL,
	`rating` text NOT NULL,
	`reviews` integer NOT NULL,
	`art` integer NOT NULL,
	`verified` integer DEFAULT 1 NOT NULL,
	`active` integer DEFAULT 1 NOT NULL,
	FOREIGN KEY (`owner_id`) REFERENCES `users`(`id`) ON UPDATE no action ON DELETE no action
);
--> statement-breakpoint
CREATE TABLE `slot_locks` (
	`id` text PRIMARY KEY NOT NULL,
	`field_id` text NOT NULL,
	`date` text NOT NULL,
	`hour` integer NOT NULL,
	`kind` text NOT NULL,
	`booking_id` text,
	`reason` text,
	FOREIGN KEY (`field_id`) REFERENCES `fields`(`id`) ON UPDATE no action ON DELETE no action,
	FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON UPDATE no action ON DELETE no action
);
--> statement-breakpoint
CREATE UNIQUE INDEX `one_reservation_per_slot` ON `slot_locks` (`field_id`,`date`,`hour`);--> statement-breakpoint
CREATE TABLE `spaces` (
	`id` text PRIMARY KEY NOT NULL,
	`commission` integer DEFAULT 8 NOT NULL,
	`active_user_id` text,
	`created_at` integer NOT NULL
);
--> statement-breakpoint
CREATE TABLE `users` (
	`id` text PRIMARY KEY NOT NULL,
	`space` text NOT NULL,
	`phone` text NOT NULL,
	`name` text NOT NULL,
	`role` text NOT NULL,
	`status` text DEFAULT 'active' NOT NULL,
	`created_at` integer NOT NULL,
	FOREIGN KEY (`space`) REFERENCES `spaces`(`id`) ON UPDATE no action ON DELETE no action
);
--> statement-breakpoint
CREATE UNIQUE INDEX `users_phone_unique` ON `users` (`space`,`phone`);