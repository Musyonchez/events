<?php

// models/model/User.php
class User
{
    public string $id;

    public string $full_name;

    public string $email;

    public string $student_id;

    public string $password_hash;

    public string $avatar_url;

    public static function fromArray(array $data): User
    {
        $user = new User;
        $user->id = bin2hex(random_bytes(16));
        $user->full_name = $data['full_name'];
        $user->email = $data['email'];
        $user->student_id = $data['student_id'];
        $user->password_hash = password_hash($data['password'], PASSWORD_BCRYPT);
        $user->avatar_url = $data['avatar_url'];

        return $user;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'student_id' => $this->student_id,
            'password_hash' => $this->password_hash,
            'avatar_url' => $this->avatar_url,
        ];
    }
}
