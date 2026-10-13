<?php

Session::checkRight(PluginUploadsccglpiUploadedFile::$rightname, READ);

PluginUploadsccglpiUploadPanel::exportReport();
