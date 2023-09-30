<?php

// this is part of downgrade build
declare (strict_types=1);
namespace RectorPrefix202309;

use RectorPrefix202309\Nette\Utils\FileSystem;
use RectorPrefix202309\Nette\Utils\Json;
require __DIR__ . '/../vendor/autoload.php';
$composerJsonFileContents = FileSystem::read(__DIR__ . '/../composer.json');
$composerJson = Json::decode($composerJsonFileContents, Json::FORCE_ARRAY);
$composerJson['replace']['phpstan/phpstan'] = $composerJson['require']['phpstan/phpstan'];
$modifiedComposerJsonFileContents = Json::encode($composerJson, Json::PRETTY);
FileSystem::write(__DIR__ . '/../composer.json', $modifiedComposerJsonFileContents);
echo 'Done!' . \PHP_EOL;