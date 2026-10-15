<?php

use App\Enums\AccountRole;
use App\Livewire\CommentThread;
use App\Models\Account;
use App\Models\Comment;
use App\Models\CommentAttachment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * A minimal but real .docx (a zip with the Office parts), so content sniffing sees what a browser would upload.
 */
function realDocx(): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'docx');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
    $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>');
    $zip->close();

    return UploadedFile::fake()->createWithContent('report.docx', file_get_contents($path));
}

/**
 * Submit one attachment through the comment thread the way a client could, bypassing any file input.
 * Livewire's test harness reports the type a fake file was given (guessed from its name), where a real
 * upload is sniffed from its content, so the file is told what finfo detects in its bytes.
 */
function submitAttachment(UploadedFile $file): Testable
{
    $file->mimeType((new finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath()));

    return Livewire::actingAs(test()->member)
        ->test(CommentThread::class, ['record' => test()->task])
        ->set('body', 'See attached file.')
        ->set('attachments', [$file])
        ->call('addComment');
}

beforeEach(function () {
    Storage::fake('local');

    $owner = User::factory()->create();
    $this->member = User::factory()->create();
    $account = Account::factory()->for($owner, 'owner')->create();
    $account->users()->attach($this->member, ['role' => AccountRole::Member->value]);
    $this->task = Task::factory()->for($account)->create([
        'client_id' => null,
        'project_id' => null,
        'assigned_user_id' => $this->member->id,
    ]);
});

it('stores files whose name and content are on the allowlist', function (Closure $makeFile) {
    submitAttachment($makeFile())->assertHasNoErrors();

    expect(CommentAttachment::count())->toBe(1);
})->with([
    'png' => [fn () => UploadedFile::fake()->image('photo.png', 20, 20)],
    'pdf' => [fn () => UploadedFile::fake()->createWithContent('brief.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n")],
    'csv' => [fn () => UploadedFile::fake()->createWithContent('rows.csv', "name,amount\nAcme,100\n")],
    'docx' => [fn () => realDocx()],
]);

it('refuses files that are not on the allowlist and stores nothing', function (Closure $makeFile) {
    submitAttachment($makeFile())->assertHasErrors(['attachments.0']);

    expect(CommentAttachment::count())->toBe(0)
        ->and(Comment::count())->toBe(0);
})->with([
    'svg' => [fn () => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')],
    'html' => [fn () => UploadedFile::fake()->createWithContent('page.html', '<html><body><script>alert(1)</script></body></html>')],
    'exe' => [fn () => UploadedFile::fake()->createWithContent('setup.exe', "MZ\x90\x00\x03\x00\x00\x00\x04\x00\x00\x00\xff\xff")],
    'svg markup named png' => [fn () => UploadedFile::fake()->createWithContent('photo.png', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')],
    'html named txt' => [fn () => UploadedFile::fake()->createWithContent('notes.txt', '<html><body><script>alert(1)</script></body></html>')],
    'executable named pdf' => [fn () => UploadedFile::fake()->createWithContent('invoice.pdf', "MZ\x90\x00\x03\x00\x00\x00\x04\x00\x00\x00\xff\xff")],
]);

it('keeps svg out of the Livewire preview types', function () {
    expect(config('livewire.temporary_file_upload.preview_mimes'))->not->toContain('svg');
});

it('keeps unsafe types out of both allowlists', function () {
    expect(config('comments.attachments.allowed_mimetypes'))
        ->not->toContain('application/octet-stream', 'image/svg+xml', 'text/html')
        ->and(config('comments.attachments.allowed_extensions'))
        ->not->toContain('svg', 'html', 'htm', 'exe');
});
