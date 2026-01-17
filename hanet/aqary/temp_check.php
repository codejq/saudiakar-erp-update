<?php
include 'connectdb.hnt';

echo "Checking problematic records:\n\n";

// Check aksat + amlakdetails
$q = mysql_query("
    SELECT
        aksat.idsubaqar,
        aksat.egar_id as aksat_egar_id,
        aksat.kastdate,
        amlakdetails.rakam3akdid as amlak_rakam3akdid
    FROM aksat
    INNER JOIN amlakdetails ON aksat.idsubaqar = amlakdetails.idsubaqar
    WHERE aksat.idsubaqar IN (929, 754)
    LIMIT 5
", $link);

echo "Records from aksat + amlakdetails:\n";
while($r = mysql_fetch_assoc($q)) {
    echo json_encode($r) . "\n";
}

// Check if contracts exist
echo "\nChecking if contracts exist:\n";
$q2 = mysql_query("
    SELECT rakam3akdid, st_date_, duration
    FROM egar
    WHERE rakam3akdid IN (3189, 3190)
", $link);

while($r = mysql_fetch_assoc($q2)) {
    echo json_encode($r) . "\n";
}

// Check using aksat.egar_id instead
echo "\nChecking using aksat.egar_id:\n";
$q3 = mysql_query("
    SELECT DISTINCT
        aksat.idsubaqar,
        aksat.egar_id,
        egar.rakam3akdid,
        egar.st_date_,
        egar.duration
    FROM aksat
    LEFT JOIN egar ON aksat.egar_id = egar.rakam3akdid
    WHERE aksat.idsubaqar IN (929, 754)
    LIMIT 5
", $link);

while($r = mysql_fetch_assoc($q3)) {
    echo json_encode($r) . "\n";
}
