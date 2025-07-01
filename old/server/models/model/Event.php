<?php

// models/model/Event.php

class Event
{
    public string $id;

    public string $event_name;

    public string $event_date;

    public string $event_time;

    public string $location;

    public string $description;

    public string $host;

    public ?string $special_activities = null;

    /** @var array<int, array{time: string, activity: string}> */
    public array $agenda = [];

    /** @var array<int, array{name: string, role: string}> */
    public array $speakers = [];

    /** @var array{email: string, phone: string, social: array<string, string>} */
    public array $team_contact = [];

    public string $status = 'upcoming';

    /** @var array<int, array{name: string, email: string}> */
    public array $attendees = [];

    public static function fromArray(array $data): Event
    {
        $event = new Event;

        $event->id = bin2hex(random_bytes(16));
        $event->event_name = trim($data['eventName']);
        $event->event_date = $data['eventDate'];
        $event->event_time = $data['eventTime'];
        $event->location = trim($data['eventLocation']);
        $event->description = trim($data['eventDescription']);
        $event->host = trim($data['eventHost']);
        $event->special_activities = $data['specialActivities'] ?? null;
        $event->agenda = $data['agenda'] ?? [];
        $event->speakers = $data['speakers'] ?? [];
        $event->team_contact = $data['teamContact'] ?? [];
        $event->status = $data['status'] ?? 'upcoming';
        $event->attendees = $data['attendees'] ?? [];

        return $event;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'event_name' => $this->event_name,
            'event_date' => $this->event_date,
            'event_time' => $this->event_time,
            'location' => $this->location,
            'description' => $this->description,
            'host' => $this->host,
            'special_activities' => $this->special_activities,
            'agenda' => $this->agenda,
            'speakers' => $this->speakers,
            'team_contact' => $this->team_contact,
            'status' => $this->status,
            'attendees' => $this->attendees,
        ];
    }
}
