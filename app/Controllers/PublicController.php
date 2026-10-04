<?php

declare(strict_types=1);

namespace App\Controllers;

class PublicController extends BaseController
{
    /**
     * Home page.
     */
    public function home(): void
    {
        $this->view('public/home', [
            'pageTitle' => 'Welcome',
        ], 'layouts/public');
    }

    /**
     * About page.
     */
    public function about(): void
    {
        $this->view('public/about', [
            'pageTitle' => 'About Us',
        ], 'layouts/public');
    }

    /**
     * Features page.
     */
    public function features(): void
    {
        $this->view('public/features', [
            'pageTitle' => 'Features',
        ], 'layouts/public');
    }

    /**
     * Contact page — GET shows the form.
     */
    public function contact(): void
    {
        $this->view('public/contact', [
            'pageTitle' => 'Contact Us',
            'old'       => $_SESSION['_contact_old'] ?? [],
        ], 'layouts/public');
        // Clear preserved form values after rendering
        unset($_SESSION['_contact_old']);
    }

    /**
     * Contact form — POST processes the submission.
     *
     * Persists the message to the database before showing confirmation.
     * Shows a failure message with preserved values when storage fails.
     */
    public function contactSubmit(): void
    {
        verifyCsrfToken();

        $data = [
            'name'    => trim($_POST['name'] ?? ''),
            'email'   => trim($_POST['email'] ?? ''),
            'subject' => trim($_POST['subject'] ?? ''),
            'message' => trim($_POST['message'] ?? ''),
        ];

        $errors = $this->validate($data, [
            'name'    => 'required|max:255',
            'email'   => 'required|email|max:255',
            'subject' => 'required|max:255',
            'message' => 'required|max:5000',
        ]);

        if (!empty($errors)) {
            $_SESSION['_contact_old'] = $data;
            $this->flash('error', implode(' ', $errors));
            $this->redirect(BASE_URL . '/contact');
        }

        // Persist the contact message — do NOT show success unless storage succeeds
        if (!$this->db) {
            $_SESSION['_contact_old'] = $data;
            $this->flash('error', 'Unable to save your message at this time. Please try again later.');
            $this->redirect(BASE_URL . '/contact');
        }

        try {
            $sql = "INSERT INTO contact_messages (name, email, subject, message, created_at)
                    VALUES (:name, :email, :subject, :message, NOW())";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':name'    => $data['name'],
                ':email'   => $data['email'],
                ':subject' => $data['subject'],
                ':message' => $data['message'],
            ]);
        } catch (\PDOException $e) {
            error_log('Contact form storage failed: ' . $e->getMessage());
            $_SESSION['_contact_old'] = $data;
            $this->flash('error', 'Unable to save your message at this time. Please try again later.');
            $this->redirect(BASE_URL . '/contact');
        }

        $this->flash('success', 'Thank you for your message! We will get back to you soon.');
        $this->redirect(BASE_URL . '/contact');
    }
}
