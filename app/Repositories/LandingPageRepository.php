<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;
use PDOException;

/** FAQs, trusted-by logos, and testimonials for the public landing page. */
final class LandingPageRepository
{
    /** @return list<array<string, mixed>> */
    public function listFaqsAdmin(): array
    {
        try {
            $st = Database::pdo()->query(
                'SELECT * FROM landing_faqs ORDER BY sort_order ASC, id ASC'
            );
        } catch (PDOException) {
            return [];
        }

        return $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /** @return list<array<string, mixed>> */
    public function listFaqsPublic(): array
    {
        try {
            $st = Database::pdo()->query(
                'SELECT id, question, answer_html FROM landing_faqs
                 WHERE is_active = 1 ORDER BY sort_order ASC, id ASC'
            );
        } catch (PDOException) {
            return [];
        }

        return $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * @param list<array{question:string,answer_html:string,is_active:bool}> $rows
     */
    public function replaceFaqs(array $rows): void
    {
        $pdo = Database::pdo();
        $pdo->exec('DELETE FROM landing_faqs');
        $sort = 0;
        $ins = $pdo->prepare(
            'INSERT INTO landing_faqs (question, answer_html, sort_order, is_active)
             VALUES (:q, :a, :sort, :act)'
        );
        foreach ($rows as $row) {
            $q = trim($row['question']);
            if ($q === '' && trim($row['answer_html']) === '') {
                continue;
            }
            $ins->execute([
                'q' => $q !== '' ? $q : 'Question',
                'a' => $row['answer_html'],
                'sort' => $sort,
                'act' => $row['is_active'] ? 1 : 0,
            ]);
            $sort++;
        }
    }

    /** @return list<array<string, mixed>> */
    public function listTrustedLogosAdmin(): array
    {
        try {
            $st = Database::pdo()->query(
                'SELECT * FROM landing_trusted_logos ORDER BY sort_order ASC, id ASC'
            );
        } catch (PDOException) {
            return [];
        }

        return $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /** @return list<array<string, mixed>> */
    public function listTrustedLogosPublic(): array
    {
        try {
            $st = Database::pdo()->query(
                'SELECT name, image_url, website_url FROM landing_trusted_logos
                 WHERE is_active = 1 ORDER BY sort_order ASC, id ASC'
            );
        } catch (PDOException) {
            return [];
        }

        return $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * @param list<array{name:string,image_url:string,website_url:string,is_active:bool}> $rows
     */
    public function replaceTrustedLogos(array $rows): void
    {
        $pdo = Database::pdo();
        $pdo->exec('DELETE FROM landing_trusted_logos');
        $sort = 0;
        $ins = $pdo->prepare(
            'INSERT INTO landing_trusted_logos (name, image_url, website_url, sort_order, is_active)
             VALUES (:n, :img, :web, :sort, :act)'
        );
        foreach ($rows as $row) {
            $img = trim($row['image_url']);
            if ($img === '' && trim($row['name']) === '') {
                continue;
            }
            $ins->execute([
                'n' => trim($row['name']) !== '' ? trim($row['name']) : 'Partner',
                'img' => $img,
                'web' => trim($row['website_url']) !== '' ? trim($row['website_url']) : null,
                'sort' => $sort,
                'act' => $row['is_active'] ? 1 : 0,
            ]);
            $sort++;
        }
    }

    /** @return list<array<string, mixed>> */
    public function listTestimonialsAdmin(): array
    {
        try {
            $st = Database::pdo()->query(
                'SELECT * FROM landing_testimonials ORDER BY sort_order ASC, id ASC'
            );
        } catch (PDOException) {
            return [];
        }

        return $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /** @return list<array<string, mixed>> */
    public function listTestimonialsPublic(): array
    {
        try {
            $st = Database::pdo()->query(
                'SELECT quote_html, author_name, author_detail, portrait_url FROM landing_testimonials
                 WHERE is_active = 1 ORDER BY sort_order ASC, id ASC'
            );
        } catch (PDOException) {
            return [];
        }

        return $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * @param list<array{quote_html:string,author_name:string,author_detail:string,portrait_url:string,is_active:bool}> $rows
     */
    public function replaceTestimonials(array $rows): void
    {
        $pdo = Database::pdo();
        $pdo->exec('DELETE FROM landing_testimonials');
        $sort = 0;
        $ins = $pdo->prepare(
            'INSERT INTO landing_testimonials (quote_html, author_name, author_detail, portrait_url, sort_order, is_active)
             VALUES (:q, :name, :det, :pic, :sort, :act)'
        );
        foreach ($rows as $row) {
            $name = trim($row['author_name']);
            $quote = trim($row['quote_html']);
            if ($name === '' && $quote === '') {
                continue;
            }
            $pic = trim($row['portrait_url']);
            $ins->execute([
                'q' => $quote !== '' ? $quote : '<p></p>',
                'name' => $name !== '' ? $name : 'Customer',
                'det' => trim($row['author_detail']) !== '' ? trim($row['author_detail']) : null,
                'pic' => $pic !== '' ? $pic : null,
                'sort' => $sort,
                'act' => $row['is_active'] ? 1 : 0,
            ]);
            $sort++;
        }
    }
}
