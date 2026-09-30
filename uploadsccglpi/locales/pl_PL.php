<?php
return [
    'Upload' => 'Upload',
    'Uploaded file' => 'Wgrany plik',
    'Uploaded files' => 'Wgrane pliki',
    'Upload a file' => 'Wgraj plik',
    'File to upload' => 'Plik do wgrania',
    'Too many files in one upload (limit: %d).' => 'Za dużo plików w jednym wgraniu (limit: %d).',
    'PHP caps the number of files per request (max_file_uploads: %d) and drops the rest without a word; if you selected more, they never reached GLPI.'
        => 'PHP ogranicza liczbę plików na żądanie (max_file_uploads: %d) i odrzuca resztę bez słowa; jeśli wybrano więcej, nie dotarły do GLPI.',
    'Maximum number of files per upload' => 'Maksymalna liczba plików na jedno wgranie',
    'No file has been uploaded yet.' => 'Nie wgrano jeszcze żadnego pliku.',
    'Files uploaded by every user are listed.' => 'Widzisz pliki wgrane przez wszystkich użytkowników.',
    'Only the files you uploaded yourself are listed.' => 'Widzisz tylko pliki wgrane przez siebie.',
    'No file was selected.' => 'Nie wybrano żadnego pliku.',
    'This file no longer exists.' => 'Ten plik już nie istnieje.',
    'You are not allowed to upload files.' => 'Nie masz uprawnień do wgrywania plików.',
    'Size' => 'Rozmiar',
    'Pairs uploaded: %d' => 'Wgrane pary: %d',
    'Unrecognised extension.' => 'Nierozpoznane rozszerzenie.',
    'Upload log' => 'Rejestr wgrań',
    'Only the %d most recent entries are listed.' => 'Wyświetlanych jest tylko %d ostatnich wpisów.',
    'Host' => 'Host',
    'Two files of the same kind for one host - a pair is one archive and one %s file.'
        => 'Dwa pliki tego samego rodzaju dla jednego hosta – para to jedno archiwum i jeden plik %s.',
    'The archive of this pair is missing.' => 'Brakuje archiwum dla tej pary.',
    'The matching "%s" file is missing.' => 'Brakuje pasującego pliku "%s".',
    'Result of the last upload' => 'Wynik ostatniego wgrania',
    'loaded: %d' => 'załadowane: %d',
    'refused: %d' => 'odrzucone: %d',
    'Reason' => 'Powód',
    'loaded' => 'załadowany',
    'not loaded' => 'niezaładowany',
    'No "%s" file could be read from the archive.'
        => 'Nie udało się odczytać pliku "%s" z archiwum.',
    'The inventory date (var:general::date) is missing from the .cur file.'
        => 'Brak daty inwentaryzacji (var:general::date) w pliku .cur.',
    'The inventory date "%s" could not be read.' => 'Nie udało się odczytać daty inwentaryzacji "%s".',
    'The inventory is from %s and is older than the allowed %d days.'
        => 'Inwentaryzacja jest z %s, czyli starsza niż dopuszczalne %d dni.',
    'The scc.conf version (fix:general::scc.conf:version) is missing from the .cur file.'
        => 'Brak wersji scc.conf (fix:general::scc.conf:version) w pliku .cur.',
    'The scc.conf version "%s" could not be read.' => 'Nie udało się odczytać wersji scc.conf "%s".',
    'The scc.conf version %s is too far behind %s, the current one for %s.'
        => 'Wersja scc.conf %s jest zbyt odległa od %s, bieżącej dla %s.',
    'The scc.conf version %s is %d behind %s, the current one for %s; at most %d is allowed.'
        => 'Wersja scc.conf %s jest o %d starsza od %s, bieżącej dla %s; dopuszczalne jest najwyżej %d.',
    'The system category (fix:general::scc.conf:os_category) is missing from the .cur file.'
        => 'Brak kategorii systemu (fix:general::scc.conf:os_category) w pliku .cur.',
    'Unknown system category "%s" - expected one of: %s.'
        => 'Nierozpoznana kategoria systemu „%s" – obsługiwane: %s.',
    'Maximum inventory age (days)' => 'Maksymalny wiek inwentaryzacji (dni)',
    'Newest agent versions' => 'Najnowsze wersje agenta',
    'Linux / Unix' => 'Linux / Unix',
    'Windows' => 'Windows',
    'unknown' => 'nieznana',
    'You can select up to %d files with the extension %s or %s.'
        => 'Możesz wybrać maksymalnie %d plików z rozszerzeniem %s lub %s.',
    'No newest agent version is known for %s - the "%s" knowledge base article could not be read. Contact your GLPI administrator.'
        => 'Nie jest znana najnowsza wersja agenta dla %s – nie udało się odczytać wpisu bazy wiedzy „%s". Skontaktuj się z administratorem GLPI.',
    'Maximum version gap' => 'Maksymalna różnica wersji',
    'Files' => 'Pliki',
    'incomplete pair' => 'niekompletna para',
    'Archive extensions' => 'Rozszerzenia archiwum',
    'Marker extension' => 'Rozszerzenie sygnału',
    'Only these extensions are accepted: %s.' => 'Akceptowane są wyłącznie rozszerzenia: %s.',
    'The file is empty.' => 'Plik jest pusty.',
    'The file exceeds the maximum allowed size (%s).' => 'Plik przekracza maksymalny dozwolony rozmiar (%s).',
    'The file was only partially uploaded.' => 'Plik został wysłany tylko częściowo.',
    'The file was not received by the server.' => 'Plik nie dotarł na serwer.',
    'The file could not be registered in the database.' => 'Nie udało się zarejestrować pliku w bazie danych.',
    'Storage directory "%s" does not exist or is not writable.'
        => 'Katalog docelowy "%s" nie istnieje lub nie ma do niego prawa zapisu.',
    'The storage directory is not writable - contact your GLPI administrator.'
        => 'Katalog docelowy nie ma prawa zapisu – skontaktuj się z administratorem GLPI.',
    'Storage directory' => 'Katalog docelowy',
    'Maximum size per file (MB)' => 'Maksymalny rozmiar pliku (MB)',
    'writable' => 'zapisywalny',
    'missing or read-only' => 'brak katalogu lub tylko do odczytu',
    'Article "%s" not found - the last saved versions apply.'
        => 'Nie znaleziono wpisu „%s" – obowiązują ostatnio zapisane wersje.',
    'Article renamed to "%s".' => 'Wpis ma zmienioną nazwę: „%s".',
    'No version could be read from article "%s" - the last saved versions apply.'
        => 'Nie udało się odczytać wersji z wpisu „%s" – obowiązują ostatnio zapisane wersje.',
    'Article "%s" does not give a version for every system.'
        => 'Wpis „%s" nie podaje wersji dla wszystkich systemów.',
    'Article "%s" could not be read - the last saved versions apply.'
        => 'Błąd odczytu wpisu „%s" – obowiązują ostatnio zapisane wersje.',
];
