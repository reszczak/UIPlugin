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
    'PHP on this server accepts %d files per request (max_file_uploads), so this setting is the one that applies.'
        => 'PHP na tym serwerze przyjmuje %d plików na żądanie (max_file_uploads), więc obowiązuje to ustawienie.',
    'WARNING: PHP on this server accepts only %d files per request (max_file_uploads) and drops the rest without a word. Raise that directive, or lower this setting to match.'
        => 'UWAGA: PHP na tym serwerze przyjmuje tylko %d plików na żądanie (max_file_uploads) i odrzuca resztę bez słowa. Podnieś tę dyrektywę albo obniż to ustawienie.',
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
    'Content checks' => 'Walidacja zawartości',
    'Read from the .cur file inside the archive, before anything is written to disk. An archive that fails either check is refused and the pair is not stored.'
        => 'Czytane z pliku .cur wewnątrz archiwum, zanim cokolwiek trafi na dysk. Archiwum, które nie przejdzie którejkolwiek z tych kontroli, jest odrzucane i para nie zostaje zapisana.',
    'Maximum inventory age (days)' => 'Maksymalny wiek inwentaryzacji (dni)',
    'Checked against var:general::date in the .cur file.'
        => 'Sprawdzane względem var:general::date w pliku .cur.',
    'Newest agent versions' => 'Najnowsze wersje agenta',
    'Linux / Unix' => 'Linux / Unix',
    'Windows' => 'Windows',
    'Read from the knowledge base article "%s" on every visit and saved in the plugin\'s database. Edit the article to raise a version - this is not a setting of the plugin. If the article disappears or can no longer be read, the last saved version stays in force.'
        => 'Czytane z wpisu bazy wiedzy „%s" przy każdym wejściu i zapisywane w bazie wtyczki. Żeby podnieść wersję, edytuj ten wpis – to nie jest ustawienie wtyczki. Jeśli wpis zniknie albo nie da się go odczytać, obowiązuje ostatnio zapisana wersja.',
    'Open the knowledge base' => 'Otwórz bazę wiedzy',
    'Open the article' => 'Otwórz wpis',
    'Which line applies is decided per archive by fix:general::scc.conf:os_category. %s map to Linux / Unix; Windows has its own.'
        => 'To, która linia obowiązuje, rozstrzyga dla każdego archiwum wpis fix:general::scc.conf:os_category. %s trafiają na linię Linux / Unix; Windows ma własną.',
    'System' => 'System',
    'Version' => 'Wersja',
    'Source' => 'Źródło',
    'Changed' => 'Zmieniona',
    'Last read from the article' => 'Ostatni odczyt z wpisu',
    'knowledge base article' => 'wpis bazy wiedzy',
    'last saved copy' => 'ostatnio zapisana',
    'unknown - uploads from this system are refused' => 'nieznana – archiwa z tego systemu są odrzucane',
    'unknown' => 'nieznana',
    'none' => 'brak',
    'You can select up to %d files with the extension %s or %s.'
        => 'Możesz wybrać maksymalnie %d plików z rozszerzeniem %s lub %s.',
    'The knowledge base article "%s" was not found. The last saved versions stay in force until it is back.'
        => 'Nie znaleziono wpisu bazy wiedzy „%s". Do czasu jego powrotu obowiązują ostatnio zapisane wersje.',
    'The knowledge base article was renamed to "%s". Versions are still read from it, but give it back the title "%s" so it is found by name.'
        => 'Nazwa wpisu bazy wiedzy została zmieniona na „%s". Wersje są nadal z niego czytane, ale przywróć mu tytuł „%s", żeby był odnajdywany po nazwie.',
    'No version could be read from the knowledge base article "%s" - its content has changed. The last saved versions stay in force.'
        => 'Z wpisu bazy wiedzy „%s" nie udało się odczytać żadnej wersji – jego treść się zmieniła. Obowiązują ostatnio zapisane wersje.',
    'The knowledge base article "%s" does not state a version for every system. For those, the last saved version stays in force.'
        => 'Wpis bazy wiedzy „%s" nie podaje wersji dla każdego systemu. Dla brakujących obowiązuje ostatnio zapisana wersja.',
    'The knowledge base article "%s" could not be read because of a database error. The last saved versions stay in force.'
        => 'Nie udało się odczytać wpisu bazy wiedzy „%s" z powodu błędu bazy danych. Obowiązują ostatnio zapisane wersje.',
    'No newest agent version is known for %s - the "%s" knowledge base article could not be read. Contact your GLPI administrator.'
        => 'Nie jest znana najnowsza wersja agenta dla %s – nie udało się odczytać wpisu bazy wiedzy „%s". Skontaktuj się z administratorem GLPI.',
    'Maximum version gap' => 'Maksymalna różnica wersji',
    'An archive this far behind the newest version, or further, is refused. Accepted at the moment - %s and newer.'
        => 'Archiwum starsze od najnowszej wersji o tyle lub więcej jest odrzucane. Obecnie akceptowane – %s i nowsze.',
    'Files' => 'Pliki',
    'incomplete pair' => 'niekompletna para',
    'Archive extensions' => 'Rozszerzenia archiwum',
    'The data half of a pair. Comma separated, without the leading dot; compound extensions are allowed (e.g. tar.gz,gz) and the longest one wins when splitting a name.'
        => 'Połowa pary z danymi. Rozdzielone przecinkami, bez kropki na początku; dozwolone są rozszerzenia złożone (np. tar.gz,gz), a przy rozdzielaniu nazwy wygrywa najdłuższe.',
    'Marker extension' => 'Rozszerzenie sygnału',
    'The other half of a pair, sharing the name of the archive (e.g. signal).'
        => 'Druga połowa pary, o tej samej nazwie co archiwum (np. signal).',
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
    'Files uploaded from the Upload page are written to a fixed directory on the server and indexed in the database.'
        => 'Pliki wgrywane na stronie Upload trafiają do stałego katalogu na serwerze i są indeksowane w bazie.',
    'Storage directory' => 'Katalog docelowy',
    'This path is fixed and cannot be changed from GLPI. To have another tool read the files from elsewhere, symlink this directory.'
        => 'Ścieżka jest stała i nie da się jej zmienić z poziomu GLPI. Jeśli inne narzędzie ma czytać te pliki z innego miejsca, podlinkuj ten katalog (symlink).',
    'Maximum size per file (MB)' => 'Maksymalny rozmiar pliku (MB)',
    'PHP itself refuses anything above %s (upload_max_filesize / post_max_size), so the effective limit is %s.'
        => 'Samo PHP odrzuca cokolwiek powyżej %s (upload_max_filesize / post_max_size), więc realny limit to %s.',
    'writable' => 'zapisywalny',
    'missing or read-only' => 'brak katalogu lub tylko do odczytu',
    'The directory is created automatically on the first upload if its parent is writable by the web server user.'
        => 'Katalog zostanie utworzony automatycznie przy pierwszym wgraniu pliku, o ile użytkownik serwera WWW ma prawo zapisu do katalogu nadrzędnego.',
];
