<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FileUploadServiceTest extends TestCase
{
    public static function invalidUploads(): array
    {
        return [
            'structure absente' => [[], 'Structure de fichier invalide.'],
            'aucun fichier' => [['error' => UPLOAD_ERR_NO_FILE], "Aucun fichier n'a été sélectionné."],
            'envoi partiel' => [['error' => UPLOAD_ERR_PARTIAL], 'partiellement'],
        ];
    }

    #[DataProvider('invalidUploads')]
    public function testInvalidUploadIsRejected(array $file, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        (new FileUploadService())->uploadImage($file, sys_get_temp_dir());
    }

    public function testOversizedUploadIsRejectedBeforeReadingTheFile(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('taille maximale');

        (new FileUploadService(10))->uploadImage([
            'error' => UPLOAD_ERR_OK,
            'size' => 11,
            'tmp_name' => 'unused',
        ], sys_get_temp_dir());
    }
}
