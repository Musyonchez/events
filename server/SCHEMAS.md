# API Schemas

This document outlines the data schemas used in the API, providing a reference for frontend development and understanding the structure of data objects.

## Club Schema

| Field Name        | Type         | Required | Default     | Constraints                                                              |
|-------------------|--------------|----------|-------------|--------------------------------------------------------------------------|
| `name`            | `string`     | Yes      |             | Min length: 3, Max length: 100                                           |
| `description`     | `string`     | Yes      |             | Min length: 10, Max length: 1000                                         |
| `category`        | `string`     | Yes      |             | Allowed: Arts & Culture, Academic, Sports, Technology, Business, Community Service, Religious, Professional, Recreation, Special Interest |
| `logo`            | `string`     | No       | `""`        | Max length: 500                                                          |
| `contact_email`   | `email`      | Yes      |             | Max length: 100                                                          |
| `leader_id`       | `objectid`   | Yes      |             |                                                                          |
| `members_count`   | `int`        | No       | `0`         | Min: 0, Max: 10000                                                       |
| `status`          | `string`     | No       | `active`    | Allowed: active, inactive                                                |

## Comment Schema

| Field Name        | Type         | Required | Default     | Constraints                                                              |
|-------------------|--------------|----------|-------------|--------------------------------------------------------------------------|
| `event_id`        | `objectid`   | Yes      |             |                                                                          |
| `user_id`         | `objectid`   | Yes      |             |                                                                          |
| `content`         | `string`     | Yes      |             | Min length: 1, Max length: 1000                                          |
| `parent_comment_id`| `objectid`   | No       | `null`      |                                                                          |
| `status`          | `string`     | No       | `pending`   | Allowed: pending, approved, rejected                                     |
| `flagged`         | `bool`       | No       | `false`     |                                                                          |

## Event Schema

| Field Name            | Type             | Required | Default     | Constraints                                                              |
|-----------------------|------------------|----------|-------------|--------------------------------------------------------------------------|
| `title`               | `string`         | No       | `""`        | Min length: 3, Max length: 200                                           |
| `description`         | `string`         | No       | `""`        | Min length: 10, Max length: 2000                                         |
| `club_id`             | `objectid`       | Yes      |             |                                                                          |
| `organizer_id`        | `objectid`       | Yes      |             |                                                                          |
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
