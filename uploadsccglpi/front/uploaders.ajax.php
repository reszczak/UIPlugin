<?php

Session::checkLoginUser();

header('Content-Type: application/json; charset=utf-8');

$users = [];

if (PluginUploadsccglpiUploadedFile::canSeeEveryUpload()) {
    $query = trim((string) ($_GET['q'] ?? ''));

    foreach (array_slice(PluginUploadsccglpiUploadedFile::searchUsers($query, 10), 0, 10) as $userId) {
        $user = new User();
        if (!$user->getFromDB($userId)) {
            continue;
        }
        $users[] = [
            'id'    => $userId,
            'label' => getUserName($userId),
            'login' => (string) $user->fields['name'],
        ];
    }
}

echo json_encode(['users' => $users]);
