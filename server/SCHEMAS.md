# Database Schemas and Data Validation Rules

## Overview for AI Agents
This document defines the exact data structures, validation rules, and constraints used throughout the USIU Events system. Each schema includes field types, validation rules, default values, and business logic constraints.

## Schema Validation Implementation
All schemas are implemented in PHP using a centralized validation system:
- **Location**: `/server/schemas/` directory
- **Validation Method**: `mapAndValidate()` for creation, `mapForUpdate()` for updates
- **Error Format**: Returns array of field-specific error messages
- **Type Casting**: Automatic conversion to appropriate PHP/MongoDB types

## Club Schema

**MongoDB Collection**: `clubs`  
**PHP Model**: `ClubModel` (`/server/models/Club.php`)  
**Validation Schema**: `ClubSchema` (`/server/schemas/Club.php`)

| Field Name        | Type         | Required | Default     | Constraints & Business Rules                                             |
|-------------------|--------------|----------|-------------|--------------------------------------------------------------------------|
| `_id`             | `ObjectId`   | Auto     | Generated   | MongoDB auto-generated unique identifier                                 |
| `name`            | `string`     | Yes      |             | Min: 3, Max: 100 chars. Must be unique. No reserved words (admin, system, test, usiu, university). No excessive special chars |
| `description`     | `string`     | Yes      |             | Min: 10, Max: 1000 chars. Content validation against inappropriate words |
| `category`        | `string`     | Yes      |             | Enum: Arts & Culture, Academic, Sports, Technology, Business, Community Service, Religious, Professional, Recreation, Special Interest |
| `logo`            | `string`     | No       | `""`        | S3 URL, Max: 500 chars. Supports JPEG, PNG, GIF, WebP formats           |
| `contact_email`   | `email`      | Yes      |             | Must end with @usiu.ac.ke. Max: 100 chars                               |
| `leader_id`       | `ObjectId`   | Yes      |             | Must reference existing user with role 'club_leader' or 'admin'         |
| `members_count`   | `int`        | No       | `0`         | Min: 0, Max: 10000. Auto-calculated, not user-editable                  |
| `members`         | `ObjectId[]` | No       | `[]`        | Array of user IDs. Max 10000 members                                    |
| `status`          | `string`     | No       | `active`    | Enum: active, inactive                                                   |
| `created_at`      | `UTCDateTime`| Auto     | Now         | Automatically set on creation                                            |
| `updated_at`      | `UTCDateTime`| Auto     | Now         | Automatically updated on modification                                    |

**Business Logic**:
- Club names must be unique across the system
- Only users with role 'club_leader' or 'admin' can create clubs
- Leader must be an existing verified user
- Members array is managed via `addMember()`/`removeMember()` methods
- Inactive clubs cannot create new events

## Comment Schema

**MongoDB Collection**: `comments`  
**PHP Model**: `CommentModel` (`/server/models/Comment.php`)  
**Validation Schema**: `CommentSchema` (`/server/schemas/Comment.php`)

| Field Name          | Type         | Required | Default     | Constraints & Business Rules                                           |
|---------------------|--------------|----------|-------------|------------------------------------------------------------------------|
| `_id`               | `ObjectId`   | Auto     | Generated   | MongoDB auto-generated unique identifier                               |
| `event_id`          | `ObjectId`   | Yes      |             | Must reference existing published event                                |
| `user_id`           | `ObjectId`   | Yes      |             | Must reference existing verified user                                  |
| `content`           | `string`     | Yes      |             | Min: 1, Max: 1000 chars. HTML stripped, profanity filtered            |
| `parent_comment_id` | `ObjectId`   | No       | `null`      | Single-level replies only. Must reference existing comment            |
| `status`            | `string`     | No       | `pending`   | Enum: pending, approved, rejected. Controls public visibility         |
| `flagged`           | `bool`       | No       | `false`     | Admin moderation flag. Independent of status                          |
| `user`              | `Object`     | No       | Populated   | Populated via aggregation in admin views only                         |
| `event`             | `Object`     | No       | Populated   | Populated via aggregation with event details                          |
| `event_title`       | `string`     | No       | Computed    | Quick access field from aggregation pipeline                          |
| `created_at`        | `UTCDateTime`| Auto     | Now         | Automatically set on creation                                          |
| `updated_at`        | `UTCDateTime`| Auto     | Now         | Updated on status/flag changes                                         |

**Business Logic**:
- Only approved comments visible to public users
- Users can only comment on published events
- Single-level threading (replies to replies not allowed)
- Admins can approve/reject and flag/unflag independently
- Flagged comments require admin review before approval
- User data populated only in admin aggregation queries

## Event Schema

| Field Name            | Type             | Required | Default     | Constraints                                                              |
|-----------------------|------------------|----------|-------------|--------------------------------------------------------------------------|
| `title`               | `string`         | No       | `""`        | Min length: 3, Max length: 200                                           |
| `description`         | `string`         | No       | `""`        | Min length: 10, Max length: 2000                                         |
| `club_id`             | `objectid`       | Yes      |             |                                                                          |
                                                                          |
| `event_date`          | `datetime`       | Yes      |             |                                                                          |
| `end_date`            | `datetime`       | No       | `null`      |                                                                          |
| `location`            | `string`         | No       | `""`        | Min length: 2, Max length: 200                                           |
| `venue_capacity`      | `int`            | No       | `0`         | Min: 0, Max: 50000                                                       |
| `registration_required`| `bool`           | No       | `false`     |                                                                          |
| `registration_deadline`| `datetime`       | No       | `null`      |                                                                          |
| `registration_fee`    | `float`          | No       | `0`         | Min: 0, Max: 10000                                                       |
| `max_attendees`       | `int`            | No       | `0`         | Min: 0, Max: 50000                                                       |
| `current_registrations`| `int`            | No       | `0`         | Min: 0                                                                   |
| `banner_image`        | `string`         | No       | `""`        | Max length: 500                                                          |
| `gallery`             | `string_array`   | No       | `[]`        | Max items: 20                                                            |
| `category`            | `string`         | No       | `""`        | Max length: 100                                                          |
| `tags`                | `string_array`   | No       | `[]`        | Max items: 10                                                            |
| `status`              | `string`         | No       | `draft`     | Allowed: draft, published, cancelled, completed                          |
| `featured`            | `bool`           | No       | `false`     |                                                                          |
| `registered_users`    | `objectid_array` | No       | `[]`        |                                                                          |

## User Schema

| Field Name                | Type         | Required | Default     | Constraints                                                              |
|---------------------------|--------------|----------|-------------|--------------------------------------------------------------------------|
| `student_id`              | `string`     | Yes      |             | Min length: 8, Max length: 20                                            |
| `first_name`              | `string`     | Yes      |             | Min length: 2, Max length: 50                                            |
| `last_name`               | `string`     | Yes      |             | Min length: 2, Max length: 50                                            |
| `email`                   | `email`      | Yes      |             | Max length: 100                                                          |
| `password`                | `string`     | Yes      |             | Min length: 8, Max length: 255                                           |
| `phone`                   | `string`     | No       | `""`        | Max length: 20                                                           |
| `course`                  | `string`     | No       | `""`        | Max length: 100                                                          |
| `year_of_study`           | `int`        | No       | `1`         | Min: 1, Max: 6                                                           |
| `profile_image`           | `string`     | No       | `""`        | Max length: 500                                                          |
| `role`                    | `string`     | No       | `student`   | Allowed: student, admin, club_leader                                     |
| `status`                  | `string`     | No       | `active`    | Allowed: active, inactive, suspended                                     |
| `last_login`              | `datetime`   | No       | `null`      |                                                                          |
| `refresh_token`           | `string`     | No       | `null`      | Max length: 255                                                          |
| `refresh_token_expires_at`| `datetime`   | No       | `null`      |                                                                          |
| `email_verification_token`| `string`     | No       | `null`      | Max length: 255                                                          |
| `email_verified_at`       | `datetime`   | No       | `null`      |                                                                          |
| `is_email_verified`       | `bool`       | No       | `false`     |                                                                          |
| `password_reset_token`    | `string`     | No       | `null`      | Max length: 255                                                          |
| `password_reset_expires_at`| `datetime`   | No       | `null`      |                                                                          |
