<?php

// models/schemas/EventSchema.php

class EventSchema
{
    public static function validate($data)
    {
        $errors = [];

        // Required fields validation
        $requiredFields = ['eventName', 'eventDate', 'eventTime', 'eventLocation', 'eventDescription', 'eventHost'];

        foreach ($requiredFields as $field) {
            if (! isset($data[$field]) || empty(trim($data[$field]))) {
                $errors[] = "Field '{$field}' is required";
            }
        }

        // Event name validation
        if (isset($data['eventName'])) {
            if (! is_string($data['eventName'])) {
                $errors[] = 'Event name must be a string';
            } elseif (strlen(trim($data['eventName'])) < 3) {
                $errors[] = 'Event name must be at least 3 characters long';
            } elseif (strlen(trim($data['eventName'])) > 200) {
                $errors[] = 'Event name must not exceed 200 characters';
            }
        }

        // Date validation
        if (isset($data['eventDate'])) {
            if (! is_string($data['eventDate'])) {
                $errors[] = 'Event date must be a string';
            } elseif (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['eventDate'])) {
                $errors[] = 'Event date must be in YYYY-MM-DD format';
            } elseif (! strtotime($data['eventDate'])) {
                $errors[] = 'Event date must be a valid date';
            } elseif (strtotime($data['eventDate']) < strtotime('today')) {
                $errors[] = 'Event date cannot be in the past';
            }
        }

        // Time validation
        if (isset($data['eventTime'])) {
            if (! is_string($data['eventTime'])) {
                $errors[] = 'Event time must be a string';
            } elseif (! preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $data['eventTime'])) {
                $errors[] = 'Event time must be in HH:MM format';
            }
        }

        // Location validation
        if (isset($data['eventLocation'])) {
            if (! is_string($data['eventLocation'])) {
                $errors[] = 'Event location must be a string';
            } elseif (strlen(trim($data['eventLocation'])) < 3) {
                $errors[] = 'Event location must be at least 3 characters long';
            } elseif (strlen(trim($data['eventLocation'])) > 200) {
                $errors[] = 'Event location must not exceed 200 characters';
            }
        }

        // Description validation
        if (isset($data['eventDescription'])) {
            if (! is_string($data['eventDescription'])) {
                $errors[] = 'Event description must be a string';
            } elseif (strlen(trim($data['eventDescription'])) < 10) {
                $errors[] = 'Event description must be at least 10 characters long';
            } elseif (strlen(trim($data['eventDescription'])) > 2000) {
                $errors[] = 'Event description must not exceed 2000 characters';
            }
        }

        // Host validation
        if (isset($data['eventHost'])) {
            if (! is_string($data['eventHost'])) {
                $errors[] = 'Event host must be a string';
            } elseif (strlen(trim($data['eventHost'])) < 2) {
                $errors[] = 'Event host must be at least 2 characters long';
            } elseif (strlen(trim($data['eventHost'])) > 150) {
                $errors[] = 'Event host must not exceed 150 characters';
            }
        }

        // Optional field validations

        // Special activities validation
        if (isset($data['specialActivities']) && ! empty($data['specialActivities'])) {
            if (! is_string($data['specialActivities'])) {
                $errors[] = 'Special activities must be a string';
            } elseif (strlen($data['specialActivities']) > 1000) {
                $errors[] = 'Special activities must not exceed 1000 characters';
            }
        }

        // Agenda validation
        if (isset($data['agenda'])) {
            if (! is_array($data['agenda'])) {
                $errors[] = 'Agenda must be an array';
            } else {
                foreach ($data['agenda'] as $index => $item) {
                    if (! is_array($item)) {
                        $errors[] = "Agenda item {$index} must be an array";

                        continue;
                    }

                    if (! isset($item['time']) || ! isset($item['activity'])) {
                        $errors[] = "Agenda item {$index} must have 'time' and 'activity' fields";

                        continue;
                    }

                    if (! preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $item['time'])) {
                        $errors[] = "Agenda item {$index} time must be in HH:MM format";
                    }

                    if (! is_string($item['activity']) || strlen(trim($item['activity'])) < 3) {
                        $errors[] = "Agenda item {$index} activity must be at least 3 characters long";
                    }
                }
            }
        }

        // Speakers validation
        if (isset($data['speakers'])) {
            if (! is_array($data['speakers'])) {
                $errors[] = 'Speakers must be an array';
            } else {
                foreach ($data['speakers'] as $index => $speaker) {
                    if (! is_array($speaker)) {
                        $errors[] = "Speaker {$index} must be an array";

                        continue;
                    }

                    if (! isset($speaker['name']) || ! isset($speaker['role'])) {
                        $errors[] = "Speaker {$index} must have 'name' and 'role' fields";

                        continue;
                    }

                    if (! is_string($speaker['name']) || strlen(trim($speaker['name'])) < 2) {
                        $errors[] = "Speaker {$index} name must be at least 2 characters long";
                    }

                    if (! is_string($speaker['role']) || strlen(trim($speaker['role'])) < 2) {
                        $errors[] = "Speaker {$index} role must be at least 2 characters long";
                    }
                }
            }
        }

        // Team contact validation
        if (isset($data['teamContact'])) {
            if (! is_array($data['teamContact'])) {
                $errors[] = 'Team contact must be an array';
            } else {
                $teamContact = $data['teamContact'];

                // Email validation
                if (isset($teamContact['email']) && ! empty($teamContact['email'])) {
                    if (! filter_var($teamContact['email'], FILTER_VALIDATE_EMAIL)) {
                        $errors[] = 'Team contact email must be a valid email address';
                    }
                }

                // Phone validation
                if (isset($teamContact['phone']) && ! empty($teamContact['phone'])) {
                    if (! preg_match('/^[\+]?[0-9\s\-\(\)]{10,20}$/', $teamContact['phone'])) {
                        $errors[] = 'Team contact phone must be a valid phone number';
                    }
                }

                // Social media URLs validation
                if (isset($teamContact['social']) && is_array($teamContact['social'])) {
                    $socialFields = ['facebook', 'twitter', 'instagram'];
                    foreach ($socialFields as $field) {
                        if (isset($teamContact['social'][$field]) && ! empty($teamContact['social'][$field])) {
                            if (! filter_var($teamContact['social'][$field], FILTER_VALIDATE_URL)) {
                                $errors[] = "Team contact {$field} must be a valid URL";
                            }
                        }
                    }
                }
            }
        }

        if (! empty($errors)) {
            throw new InvalidArgumentException(implode('; ', $errors));
        }

        return true;
    }
}
