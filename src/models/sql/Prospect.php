<?php

// Fichier : src/models/sql/Prospect.php
require_once __DIR__ . '/../../config/Database.php';

class Prospect
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function create($data)
    {
        $sql = "INSERT INTO prospects (company_name, contact_name, email, phone, event_type, status) 
                VALUES (:company_name, :contact_name, :email, :phone, :event_type, 'en attente')";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':company_name' => htmlspecialchars($data['company_name']),
            ':contact_name' => htmlspecialchars($data['contact_name']),
            ':email' => filter_var($data['email'], FILTER_SANITIZE_EMAIL),
            ':phone' => htmlspecialchars($data['phone']),
            ':event_type' => htmlspecialchars($data['event_type'])
        ]);
    }
}