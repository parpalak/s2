<?php
/**
 * @copyright 2024 Roman Parpalak
 * @license   http://opensource.org/licenses/MIT MIT
 * @package   S2
 */

declare(strict_types=1);

namespace integration;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * @group pict
 */
class PicturesCest
{
    public function _before(\IntegrationTester $I): void
    {
        $imagesDir = __DIR__ . '/../_output/images';
        $this->removeDir($imagesDir);
        if (!is_dir($imagesDir) && !mkdir($imagesDir, 0777, true) && !is_dir($imagesDir)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $imagesDir));
        }
    }

    public function testUploadAndManageFiles(\IntegrationTester $I): void
    {
        $I->login('author', 'author');

        // Upload 2 files, check output
        $this->uploadSimplePngFile($I, '', 'test1.png');
        $this->uploadSimplePngFile($I, '', 'test2.png');

        $I->amOnPage('https://localhost/_admin/ajax.php?action=load_files&path=');
        $I->seeResponseCodeIs(200);
        $I->assertJsonSubResponseContains('test1.png', [0, 'data', 'title']);
        $I->assertJsonSubResponseEquals('test1.png', [0, 'attr', 'data-fname']);
        $I->assertJsonSubResponseEquals('1*1', [0, 'attr', 'data-dim']);
        $I->assertJsonSubResponseEquals(1, [0, 'attr', 'data-bits']);
        $I->assertJsonSubResponseEquals('67 B', [0, 'attr', 'data-fsize']);

        $I->assertJsonSubResponseContains('test2.png', [1, 'data', 'title']);

        $I->amOnPage('https://localhost/_admin/ajax.php?action=preview');
        $I->seeResponseCodeIs(400);
        $I->assertJsonSubResponseContains('Parameter "file" is required.', ['message']);

        $I->amOnPage('https://localhost/_admin/ajax.php?action=preview&file=/test1.png');
        $I->seeResponseCodeIs(200);
        $I->see('ftypavif');

        // Author cannot rename files
        $I->sendPost('https://localhost/_admin/ajax.php?action=rename_file&path=/test1.png&name=test1.php');
        $I->seeResponseCodeIs(403);
        $I->assertJsonSubResponseEquals('You do not have enough permissions to perform this action.', ['message']);

        // Author cannot delete files
        $I->sendPost('https://localhost/_admin/ajax.php?action=delete_files&path=/&fname[]=test1.png');
        $I->seeResponseCodeIs(403);
        $I->assertJsonSubResponseEquals('You do not have enough permissions to perform this action.', ['message']);

        // Editor can rename files, but only if extension is allowed
        $I->logout();
        $I->login('editor', 'editor');
        $I->sendPost('https://localhost/_admin/ajax.php?action=rename_file&path=/test1.png&name=test1.php');
        $I->seeResponseCodeIs(403);
        $I->assertJsonSubResponseEquals('You are not allowed to create “php” files here. Contact administrators or developers if you really need this.', ['message']);

        $I->sendPost('https://localhost/_admin/ajax.php?action=rename_file&path=/test1.png&name=cest1.png');
        $I->seeResponseCodeIs(200);
        $I->assertJsonSubResponseEquals('cest1.png', ['new_name']);

        // Check on renaming if file with same name already exists
        $I->sendPost('https://localhost/_admin/ajax.php?action=rename_file&path=/test2.png&name=cest1.png');
        $I->seeResponseCodeIs(409);
        $I->assertJsonSubResponseEquals('Rename failed: file or folder “cest1.png” already exists.', ['message']);

        // Editor can delete files
        $I->sendPost('https://localhost/_admin/ajax.php?action=delete_files&path=/&fname[]=test2.png');
        $I->seeResponseCodeIs(200);
        $I->assertJsonSubResponseEquals(true, ['success']);

        $I->amOnPage('https://localhost/_admin/ajax.php?action=load_files&path=');
        $I->seeResponseCodeIs(200);
        $I->assertJsonSubResponseContains('cest1.png', [0, 'data', 'title']);
        $I->assertJsonResponseHasNoKey([1]);

        // Admin can rename files, even if extension is not allowed
        $I->logout();
        $I->login('admin', 'admin');
        $I->sendPost('https://localhost/_admin/ajax.php?action=rename_file&path=/cest1.png&name=test1.php');
        $I->seeResponseCodeIs(200);
        $I->assertJsonSubResponseEquals('test1.php', ['new_name']);
    }

    public function moveFilesAndFolders(\IntegrationTester $I): void
    {
        $I->login('author', 'author');

        // creat folder1/folder11, folder1/folder12
        $I->sendPost('https://localhost/_admin/ajax.php?action=create_subfolder&path=&name=folder1');
        $I->seeResponseCodeIs(200);
        $I->assertJsonSubResponseEquals('folder1', ['name']);

        $I->sendPost('https://localhost/_admin/ajax.php?action=create_subfolder&path=/folder1&name=folder11');
        $I->seeResponseCodeIs(200);
        $I->assertJsonSubResponseEquals('folder11', ['name']);

        $I->sendPost('https://localhost/_admin/ajax.php?action=create_subfolder&path=/folder1&name=folder12');
        $I->seeResponseCodeIs(200);
        $I->assertJsonSubResponseEquals('folder12', ['name']);

        $this->uploadSimplePngFile($I, '/folder1', 'test1.png');
        $this->uploadSimplePngFile($I, '/folder1', 'test2.png');
        $this->uploadSimplePngFile($I, '/folder1', 'test3.png');

        // move files
        $I->sendPost('https://localhost/_admin/ajax.php?action=move_files&spath=/folder1&dpath=/folder1/folder12&fname[]=test1.png&fname[]=test2.png');
        $I->seeResponseCodeIs(403);

        $I->logout();
        $I->login('editor', 'editor');
        $I->sendPost('https://localhost/_admin/ajax.php?action=move_files&spath=/folder1&dpath=/folder1/folder12&fname[]=test1.png&fname[]=test2.png');
        $I->seeResponseCodeIs(200);

        // move folder with files
        $I->sendPost('https://localhost/_admin/ajax.php?action=move_folder&spath=/folder1/folder12&dpath=/folder1/folder11');
        $I->seeResponseCodeIs(200);

        $I->amOnPage('https://localhost/_admin/ajax.php?action=load_files&path=/folder1/folder11/folder12');
        $I->seeResponseCodeIs(200);
        $I->assertJsonSubResponseContains('test1.png', [0, 'data', 'title']);
        $I->assertJsonSubResponseContains('test2.png', [1, 'data', 'title']);
        $I->assertJsonResponseHasNoKey([2]);

        $I->amOnPage('https://localhost/_admin/ajax.php?action=load_files&path=/folder1');
        $I->seeResponseCodeIs(200);
        $I->assertJsonSubResponseContains('test3.png', [0, 'data', 'title']);
        $I->assertJsonResponseHasNoKey([1]);
    }

    public function testNoFilesAndFolders(\IntegrationTester $I): void
    {
        $I->login('admin', 'admin');
        $I->seeResponseCodeIs(200);

        $I->amOnPage('https://localhost/_admin/ajax.php?action=load_folders');

        $I->seeResponseCodeIs(200);
        $I->assertJsonSubResponseContains('Pictures', ['data']);
        $I->assertJsonSubResponseEquals([], ['children']);
    }

    public function testManageFolders(\IntegrationTester $I): void
    {
        $I->login('admin', 'admin');
        $I->seeResponseCodeIs(200);

        // folder1/folder11, folder1/folder12
        $I->sendPost('https://localhost/_admin/ajax.php?action=create_subfolder&path=&name=folder1');
        $I->seeResponseCodeIs(200);
        $I->sendPost('https://localhost/_admin/ajax.php?action=create_subfolder&path=/folder1&name=folder11');
        $I->seeResponseCodeIs(200);
        $I->sendPost('https://localhost/_admin/ajax.php?action=create_subfolder&path=/folder1&name=folder12');
        $I->seeResponseCodeIs(200);

        // folder2/folder21, folder2/folder22
        $I->sendPost('https://localhost/_admin/ajax.php?action=create_subfolder&path=&name=folder2');
        $I->seeResponseCodeIs(200);
        $I->sendPost('https://localhost/_admin/ajax.php?action=create_subfolder&path=/folder2&name=folder21');
        $I->seeResponseCodeIs(200);
        $I->sendPost('https://localhost/_admin/ajax.php?action=create_subfolder&path=/folder2&name=folder22');
        $I->seeResponseCodeIs(200);


        $I->amOnPage('https://localhost/_admin/ajax.php?action=load_folders');

        $I->seeResponseCodeIs(200);
        $I->assertJsonSubResponseContains('Pictures', ['data']);
        $I->assertJsonSubResponseEquals('folder1', ['children', 0, 'data']);
        $I->assertJsonSubResponseEquals('folder11', ['children', 0, 'children', 0, 'data']);
        $I->assertJsonSubResponseEquals('folder12', ['children', 0, 'children', 1, 'data']);

        // folder1/folder11 -> folder2
        $I->sendPost('https://localhost/_admin/ajax.php?action=move_folder&spath=/folder1/folder11&dpath=/folder2');
        $I->seeResponseCodeIs(200);

        $I->amOnPage('https://localhost/_admin/ajax.php?action=load_folders&path=/folder1');
        $I->seeResponseCodeIs(200);
        $I->assertJsonSubResponseEquals('folder12', [0, 'data']);

        $I->amOnPage('https://localhost/_admin/ajax.php?action=load_folders&path=/folder2');
        $I->seeResponseCodeIs(200);
        $I->assertJsonSubResponseEquals('folder11', [0, 'data']);
        $I->assertJsonSubResponseEquals('folder21', [1, 'data']);
        $I->assertJsonSubResponseEquals('folder22', [2, 'data']);

        // folder2 exists, creating folder21
        $I->sendPost('https://localhost/_admin/ajax.php?action=create_subfolder&path=&name=folder2');
        $I->seeResponseCodeIs(200);
        // folder2 exists, creating folder22
        $I->sendPost('https://localhost/_admin/ajax.php?action=create_subfolder&path=&name=folder2');
        $I->seeResponseCodeIs(200);

        $I->amOnPage('https://localhost/_admin/ajax.php?action=load_folders');
        $I->seeResponseCodeIs(200);
        $I->assertJsonSubResponseContains('Pictures', ['data']);
        $I->assertJsonSubResponseEquals('folder1', ['children', 0, 'data']);
        $I->assertJsonSubResponseEquals('folder2', ['children', 1, 'data']);
        $I->assertJsonSubResponseEquals('folder21', ['children', 2, 'data']);
        $I->assertJsonSubResponseEquals('folder22', ['children', 3, 'data']);

        // renaming
        $I->sendPost('https://localhost/_admin/ajax.php?action=rename_folder&path=/folder21&name=somenewname');
        $I->seeResponseCodeIs(200);
        $I->amOnPage('https://localhost/_admin/ajax.php?action=load_folders');
        $I->seeResponseCodeIs(200);
        $I->assertJsonSubResponseContains('Pictures', ['data']);
        $I->assertJsonSubResponseEquals('somenewname', ['children', 3, 'data']);

        // Remove all
        $I->sendPost('https://localhost/_admin/ajax.php?action=delete_folder&path=/folder1');
        $I->seeResponseCodeIs(200);
        $I->sendPost('https://localhost/_admin/ajax.php?action=delete_folder&path=/folder2');
        $I->seeResponseCodeIs(200);
        $I->sendPost('https://localhost/_admin/ajax.php?action=delete_folder&path=/somenewname');
        $I->seeResponseCodeIs(200);
        $I->sendPost('https://localhost/_admin/ajax.php?action=delete_folder&path=/folder22');
        $I->seeResponseCodeIs(200);

        $I->amOnPage('https://localhost/_admin/ajax.php?action=load_folders');
        $I->seeResponseCodeIs(200);
        $I->assertJsonSubResponseContains('Pictures', ['data']);
        $I->assertJsonSubResponseEquals([], ['children']);
    }

    public function testInvalidCreateFolder(\IntegrationTester $I): void
    {
        $I->login('author', 'author');
        $I->seeResponseCodeIs(200);

        $I->sendPost('https://localhost/_admin/ajax.php?action=create_subfolder');
        $I->seeResponseCodeIs(400);
        $I->assertJsonSubResponseContains('Parameters "path" and "name" are required.', ['message']);
    }

    public function testInvalidRenameFolder(\IntegrationTester $I): void
    {
        $I->login('editor', 'editor');
        $I->seeResponseCodeIs(200);

        $I->amOnPage('https://localhost/_admin/ajax.php?action=rename_file&path=/test1.png&name=test1.php');
        $I->seeResponseCodeIs(405);
        $I->assertJsonSubResponseEquals('Only POST requests are allowed.', ['message']);

        $I->sendPost('https://localhost/_admin/ajax.php?action=rename_file&path=/test1.png');
        $I->seeResponseCodeIs(400);
        $I->assertJsonSubResponseEquals('Parameters "path" and "name" are required.', ['message']);

        $I->sendPost('https://localhost/_admin/ajax.php?action=rename_file&path=..&name=test1.php');
        $I->seeResponseCodeIs(400);
        $I->assertJsonSubResponseEquals('Invalid path.', ['message']);

        $I->sendPost('https://localhost/_admin/ajax.php?action=rename_file&path=/test1&name=./test1.php');
        $I->seeResponseCodeIs(400);
        $I->assertJsonSubResponseEquals('Invalid name.', ['message']);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it    = new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new \RecursiveIteratorIterator($it, \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($dir);
    }

    private function uploadSimplePngFile(\IntegrationTester $I, string $path, string $fileName): void
    {
        $tempFilename = tempnam("/tmp", "test_image");
        file_put_contents($tempFilename, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABAQAAAAA3bvkkAAAACklEQVR4AWNgAAAAAgABc3UBGAAAAABJRU5ErkJggg=='));

        $I->sendPost(
            'https://localhost/_admin/ajax.php?action=upload',
            ['dir' => $path],
            ['pictures' => [
                new UploadedFile($tempFilename, $fileName, 'image/png', null, true),
            ]]
        );
    }
}
