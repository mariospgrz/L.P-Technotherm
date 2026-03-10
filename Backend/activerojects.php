<?php

require_once __DIR__ . '/../admin_session.php';
require_once __DIR__ . '/../Database/Database.php';

$result = $conn->query('
 SELECT name, location, start_date, status 
 FROM projects 
 WHERE status = "active"   
 ORDER BY start_date DESC
 ');

 echo <h2>Ενεργά Έργα</h2>;
 
 
 if ($result->num_rows === 0) { 
    echo <p>Δεν υπάρχουν ενεργά έργα.</p>;
    } 
    else {
    echo <table>;
    echo <tr><th>Όνομα</th><th>Τοποθεσία</th><th>Ημερομηνία Έναρξης</th></tr>;
    while ($row = $result->fetch_assoc()) {
        echo <tr>;
        echo <td> . htmlspecialchars($row['name']) . </td>;
        echo <td> . htmlspecialchars($row['location']) . </td>;
        echo <td> . htmlspecialchars($row['start_date']) . </td>;
        echo </tr>;
    }
    echo </table>;
 }              

