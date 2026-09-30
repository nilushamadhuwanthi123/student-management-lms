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
        ], 'layouts/public');
    }

    /**
     * Contact form — POST processes the submission.
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
            $this->flash('error', implode(' ', $errors));
            $this->redirect('/contact');
        }

        // Store the contact message (using the database if available)
        if ($this->db) {
            $sql = "INSERT INTO contact_messages (name, email, subject, message, created_at)
                    VALUES (:name, :email, :subject, :message, NOW())";
            try {
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    ':name'    => $data['name'],
                    ':email'   => $data['email'],
                    ':subject' => $data['subject'],
                    ':message' => $data['message'],
                ]);
            } catch (\PDOException $e) {
                // Table may not exist yet — log and continue
                error_log('Contact form storage failed: ' . $e->getMessage());
            }
        }

        $this->flash('success', 'Thank you for your message! We will get back to you soon.');
        $this->redirect('/contact');
    }
}
