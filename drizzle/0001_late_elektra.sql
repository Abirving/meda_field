CREATE TABLE `credits` (
	`id` text PRIMARY KEY NOT NULL,
	`user_id` text NOT NULL,
	`booking_id` text NOT NULL,
	`amount` integer NOT NULL,
	`reason` text NOT NULL,
	`created_at` integer NOT NULL,
	FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON UPDATE no action ON DELETE no action,
	FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON UPDATE no action ON DELETE no action
);
--> statement-breakpoint
CREATE UNIQUE INDEX `one_credit_per_booking` ON `credits` (`booking_id`);--> statement-breakpoint
ALTER TABLE `bookings` ADD `credit_used` integer DEFAULT 0 NOT NULL;--> statement-breakpoint
ALTER TABLE `bookings` ADD `paid_amount` integer DEFAULT 0 NOT NULL;--> statement-breakpoint
ALTER TABLE `fields` ADD `woreda` text DEFAULT '03' NOT NULL;--> statement-breakpoint
ALTER TABLE `fields` ADD `sport` text DEFAULT 'FOOTBALL' NOT NULL;--> statement-breakpoint
ALTER TABLE `fields` ADD `operator_id` text;--> statement-breakpoint
ALTER TABLE `slot_locks` ADD `user_id` text;--> statement-breakpoint
ALTER TABLE `slot_locks` ADD `expires_at` integer;--> statement-breakpoint
ALTER TABLE `users` ADD `national_id` text;--> statement-breakpoint
ALTER TABLE `users` ADD `store_credit` integer DEFAULT 0 NOT NULL;--> statement-breakpoint
ALTER TABLE `users` ADD `subcity` text;--> statement-breakpoint
ALTER TABLE `users` ADD `woreda` text;--> statement-breakpoint
CREATE UNIQUE INDEX `users_national_unique` ON `users` (`space`,`national_id`);