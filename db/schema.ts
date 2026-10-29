import { sqliteTable, text, integer, real, uniqueIndex, index } from 'drizzle-orm/sqlite-core';

export const spaces = sqliteTable('spaces', {
  id: text('id').primaryKey(), commission: integer('commission').notNull().default(8),
  activeUserId: text('active_user_id'), createdAt: integer('created_at').notNull(),
});
export const users = sqliteTable('users', {
  id: text('id').primaryKey(), space: text('space').notNull().references(() => spaces.id),
  phone: text('phone').notNull(), name: text('name').notNull(), role: text('role').notNull(),
  nationalId: text('national_id'), storeCredit: integer('store_credit').notNull().default(0), subcity: text('subcity'), woreda: text('woreda'),
  status: text('status').notNull().default('active'), createdAt: integer('created_at').notNull(),
}, t => [uniqueIndex('users_phone_unique').on(t.space, t.phone), uniqueIndex('users_national_unique').on(t.space,t.nationalId)]);
export const challenges = sqliteTable('challenges', {
  id: text('id').primaryKey(), space: text('space').notNull(), phone: text('phone').notNull(),
  codeHash: text('code_hash').notNull(), attempts: integer('attempts').notNull().default(0),
  used: integer('used').notNull().default(0), expiresAt: integer('expires_at').notNull(),
  createdAt: integer('created_at').notNull(),
}, t => [index('challenge_rate_limit').on(t.space, t.phone, t.createdAt)]);
export const fields = sqliteTable('fields', {
  id: text('id').primaryKey(), space: text('space').notNull(), ownerId: text('owner_id').notNull().references(() => users.id),
  name: text('name').notNull(), subcity: text('subcity').notNull(), woreda: text('woreda').notNull().default('03'), sport: text('sport').notNull().default('FOOTBALL'), operatorId: text('operator_id'), address: text('address').notNull(),
  description: text('description').notNull(), format: text('format').notNull(),
  price: integer('price').notNull(), peakPrice: integer('peak_price').notNull(), peakStart: integer('peak_start').notNull().default(17),
  opens: integer('opens').notNull().default(6), closes: integer('closes').notNull().default(23),
  amenities: text('amenities').notNull(), rating: text('rating').notNull(), reviews: integer('reviews').notNull(),
  art: integer('art').notNull(), verified: integer('verified').notNull().default(1), active: integer('active').notNull().default(1),
});
export const bookings = sqliteTable('bookings', {
  id: text('id').primaryKey(), space: text('space').notNull(), userId: text('user_id').notNull().references(() => users.id),
  fieldId: text('field_id').notNull().references(() => fields.id), date: text('date').notNull(), hour: integer('hour').notNull(),
  amount: integer('amount').notNull(), creditUsed: integer('credit_used').notNull().default(0), paidAmount: integer('paid_amount').notNull().default(0), commission: integer('commission').notNull(), status: text('status').notNull(),
  paymentStatus: text('payment_status').notNull(), paymentMethod: text('payment_method').notNull(),
  team: text('team').notNull(), reference: text('reference').notNull().unique(), token: text('token').notNull().unique(),
  idempotencyKey: text('idempotency_key').notNull(), createdAt: integer('created_at').notNull(), checkedAt: integer('checked_at'),
}, t => [uniqueIndex('booking_idempotency').on(t.space, t.userId, t.idempotencyKey), index('booking_field_date').on(t.fieldId, t.date)]);
export const slotLocks = sqliteTable('slot_locks', {
  id: text('id').primaryKey(), fieldId: text('field_id').notNull().references(() => fields.id),
  date: text('date').notNull(), hour: integer('hour').notNull(), kind: text('kind').notNull(),
  userId: text('user_id'), expiresAt: integer('expires_at'), bookingId: text('booking_id').references(() => bookings.id), reason: text('reason'),
}, t => [uniqueIndex('one_reservation_per_slot').on(t.fieldId, t.date, t.hour)]);
export const disputes = sqliteTable('disputes', {
  id: text('id').primaryKey(), space: text('space').notNull(), bookingId: text('booking_id').notNull().references(() => bookings.id),
  userId: text('user_id').notNull(), reason: text('reason').notNull(), status: text('status').notNull().default('open'),
  resolution: text('resolution'), createdAt: integer('created_at').notNull(), resolvedAt: integer('resolved_at'),
}, t => [uniqueIndex('one_dispute_per_booking').on(t.bookingId)]);
export const audits = sqliteTable('audits', {
  id: text('id').primaryKey(), space: text('space').notNull(), actor: text('actor').notNull(),
  action: text('action').notNull(), detail: text('detail').notNull(), createdAt: integer('created_at').notNull(),
});

export const credits=sqliteTable('credits',{
 id:text('id').primaryKey(),userId:text('user_id').notNull().references(()=>users.id),bookingId:text('booking_id').notNull().references(()=>bookings.id),amount:integer('amount').notNull(),reason:text('reason').notNull(),createdAt:integer('created_at').notNull(),
},t=>[uniqueIndex('one_credit_per_booking').on(t.bookingId)]);
