<?php

Session::checkRight(PluginUploadsccglpiUploadedFile::$rightname, READ);

(new PluginUploadsccglpiUploadPanel())->run();
