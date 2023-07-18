<?php

    $zipFileName = $_GET["zipname"];
    
    // Envoyez le fichier zip au navigateur
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="'.basename($zipFileName).'"');
        header('Content-Length: ' . filesize($zipFileName));
        readfile($zipFileName);

    // Supprimez les fichiers PDF temporaire
    //foreach($pdfFiles as $pdfFile) {
    //    unlink($pdfFile);
    //}
    // Supprimez le fichier zip temporaire
    //unlink($zipFileName);