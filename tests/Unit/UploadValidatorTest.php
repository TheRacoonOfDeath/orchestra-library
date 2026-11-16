<?php

declare(strict_types=1);

namespace Orchestra\Library\Tests\Unit;

use Orchestra\Library\File\UploadRejected;
use Orchestra\Library\File\UploadValidator;
use PHPUnit\Framework\TestCase;

final class UploadValidatorTest extends TestCase
{
    private string $pdf;

    protected function setUp(): void
    {
        $this->pdf = (string) \tempnam(\sys_get_temp_dir(), 'pdf');
        \file_put_contents($this->pdf, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n");
    }

    protected function tearDown(): void
    {
        \unlink($this->pdf);
    }

    public function testAcceptsAllowedFile(): void
    {
        $file = $this->validator()->validate($this->upload('Pirates Horn 1.pdf'));

        $this->assertSame($this->pdf, $file->temporaryPath);
        $this->assertSame('Pirates-Horn-1.pdf', $file->originalName);
        $this->assertSame('application/pdf', $file->mimeType);
        $this->assertSame((int) \filesize($this->pdf), $file->size);
    }

    public function testRejectsDisallowedExtension(): void
    {
        $this->expectException(UploadRejected::class);
        $this->expectExceptionMessage('"script.php" has a file type that is not allowed.');

        $this->validator()->validate($this->upload('script.php'));
    }

    public function testRejectsContentNotMatchingTheExtension(): void
    {
        \file_put_contents($this->pdf, '<?php echo "not a pdf";');

        $this->expectException(UploadRejected::class);
        $this->validator()->validate($this->upload('fake.pdf'));
    }

    public function testRejectsFilesThatWereNotUploaded(): void
    {
        $this->expectException(UploadRejected::class);
        $this->expectExceptionMessage('was not uploaded correctly');

        (new UploadValidator(static fn (string $path): bool => false))->validate($this->upload('Horn.pdf'));
    }

    public function testRejectsUploadErrors(): void
    {
        $this->expectException(UploadRejected::class);
        $this->expectExceptionMessage('"Horn.pdf" is larger than the maximum upload size.');

        $this->validator()->validate(['error' => UPLOAD_ERR_INI_SIZE] + $this->upload('Horn.pdf'));
    }

    public function testRejectsEmptyFiles(): void
    {
        $this->expectException(UploadRejected::class);
        $this->expectExceptionMessage('"Horn.pdf" is empty.');

        $this->validator()->validate(['size' => 0] + $this->upload('Horn.pdf'));
    }

    public function testAcceptsZipAndMidiOnlyAsPieceFiles(): void
    {
        $zip = new \ZipArchive();
        $zip->open($this->pdf, \ZipArchive::OVERWRITE);
        $zip->addFromString('notes.txt', 'Rehearsal notes');
        $zip->close();

        $file = $this->validator()->validate($this->upload('Recordings.zip'), pieceFile: true);
        $this->assertSame('application/zip', $file->mimeType);

        $this->expectException(UploadRejected::class);
        $this->expectExceptionMessage('Allowed types: pdf, jpg, jpeg, png.');
        $this->validator()->validate($this->upload('Recordings.zip'));
    }

    public function testAcceptsMidiPieceFiles(): void
    {
        // The header of a MIDI file with one track.
        \file_put_contents(
            $this->pdf,
            "MThd\x00\x00\x00\x06\x00\x00\x00\x01\x00\x60MTrk\x00\x00\x00\x04\x00\xff\x2f\x00"
        );

        $file = $this->validator()->validate($this->upload('Rehearsal.mid'), pieceFile: true);

        $this->assertSame('audio/midi', $file->mimeType);
    }

    public function testExtractsNestedUploads(): void
    {
        $files = ['orchestra_part_file' => [
            'name' => [12 => 'Horn.pdf', 13 => ''],
            'type' => [12 => 'application/pdf', 13 => ''],
            'tmp_name' => [12 => '/tmp/php123', 13 => ''],
            'error' => [12 => UPLOAD_ERR_OK, 13 => UPLOAD_ERR_NO_FILE],
            'size' => [12 => 100, 13 => 0],
        ]];

        $this->assertSame(
            [
                'name' => 'Horn.pdf',
                'type' => 'application/pdf',
                'tmp_name' => '/tmp/php123',
                'error' => 0,
                'size' => 100,
            ],
            UploadValidator::extract($files, 'orchestra_part_file', 12)
        );
        $this->assertNull(UploadValidator::extract($files, 'orchestra_part_file', 13));
        $this->assertNull(UploadValidator::extract($files, 'orchestra_part_file', 14));
        $this->assertNull(UploadValidator::extract($files, 'missing'));
        $this->assertSame([12, 13], UploadValidator::keys($files, 'orchestra_part_file'));
    }

    public function testAcceptAttributeListsAllExtensions(): void
    {
        $this->assertSame('.pdf,.jpg,.jpeg,.png', UploadValidator::acceptAttribute());
        $this->assertSame('.pdf,.jpg,.jpeg,.png,.zip,.mid,.midi', UploadValidator::acceptAttribute(true));
    }

    private function validator(): UploadValidator
    {
        return new UploadValidator(static fn (string $path): bool => true);
    }

    /**
     * @return array<string, mixed>
     */
    private function upload(string $name): array
    {
        return [
            'name' => $name,
            'type' => 'application/octet-stream',
            'tmp_name' => $this->pdf,
            'error' => UPLOAD_ERR_OK,
            'size' => (int) \filesize($this->pdf),
        ];
    }
}
