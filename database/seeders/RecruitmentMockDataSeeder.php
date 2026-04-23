<?php

namespace Database\Seeders;

use App\Models\Applicant;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Recruitment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RecruitmentMockDataSeeder extends Seeder
{
    public function run(): void
    {
        $branch = Branch::firstOrCreate(
            ['BranchName' => 'Mock Recruitment Branch'],
            [
                'BranchLocation' => 'Nairobi',
                'BranchPhone' => '0700123456',
                'BranchEmail' => 'mock-branch@example.com',
            ],
        );

        $department = Department::firstOrCreate(
            ['DepartmentName' => 'Mock Recruitment Department'],
            [
                'BranchID' => $branch->BranchID,
                'DepartmentDescription' => 'Seeded department for recruitment upload testing',
                'CreatedDate' => now()->toDateString(),
            ],
        );

        $recruitment = Recruitment::firstOrCreate(
            ['JobTitle' => 'Security Guard Mock Role', 'DepartmentID' => $department->DepartmentID],
            [
                'VacancyStatus' => 'Open',
                'PostedDate' => now()->toDateString(),
            ],
        );

        $directory = 'recruitment/'.$recruitment->RecruitmentID.'/applications/mock-'.Str::uuid();

        $files = [
            'LetterOfApplication' => $this->storeMockFile($directory, 'letter-of-application.pdf', $this->pdfStub('Application Letter')),
            'HighestLevelCertificate' => $this->storeMockFile($directory, 'highest-level-certificate.pdf', $this->pdfStub('Academic Certificate')),
            'CV' => $this->storeMockFile($directory, 'cv.pdf', $this->pdfStub('Curriculum Vitae')),
            'GoodConduct' => $this->storeMockFile($directory, 'good-conduct.pdf', $this->pdfStub('Certificate of Good Conduct')),
        ];

        Applicant::updateOrCreate(
            ['NationalID' => 'MOCK-APPLICANT-001'],
            [
                'FullName' => 'Mock Applicant',
                'DateOfBirth' => '1998-04-12',
                'Email' => 'mock.applicant@example.com',
                'Address' => 'P.O. Box 123, Nairobi',
                'PhoneNumber' => '0712345678',
                'Gender' => 'Female',
                'LetterOfApplication' => $files['LetterOfApplication'],
                'HighestLevelCertificate' => $files['HighestLevelCertificate'],
                'CV' => $files['CV'],
                'ApplicationStatus' => 'Submitted',
                'GoodConduct' => $files['GoodConduct'],
                'RecruitmentID' => $recruitment->RecruitmentID,
            ],
        );
    }

    private function storeMockFile(string $directory, string $filename, string $contents): string
    {
        $path = $directory.'/'.$filename;

        Storage::disk('local')->put($path, $contents);

        return $path;
    }

    private function pdfStub(string $title): string
    {
        return implode("\n", [
            '%PDF-1.4',
            '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj',
            '2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj',
            '3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 300 144] /Contents 4 0 R >> endobj',
            '4 0 obj << /Length 44 >> stream',
            'BT /F1 12 Tf 24 100 Td ('.$title.') Tj ET',
            'endstream endobj',
            'xref',
            '0 5',
            '0000000000 65535 f ',
            'trailer << /Size 5 /Root 1 0 R >>',
            'startxref',
            '0',
            '%%EOF',
        ]);
    }
}
