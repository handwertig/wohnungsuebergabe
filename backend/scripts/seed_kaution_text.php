<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
if (is_file(__DIR__ . '/../.env')) { Dotenv\Dotenv::createImmutable(dirname(__DIR__))->load(); }
$pdo = \App\Database::pdo();

$pdo->exec("CREATE TABLE IF NOT EXISTS legal_texts (
  id         CHAR(36) NOT NULL,
  name       VARCHAR(64) NOT NULL,
  version    INT NOT NULL,
  title      VARCHAR(255) NOT NULL,
  content    MEDIUMTEXT NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_legal_name_ver (name,version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

$st=$pdo->prepare("SELECT COUNT(*) FROM legal_texts WHERE name='kaution_hinweis'");
$st->execute(); if ((int)$st->fetchColumn()===0) {
  $id  = $pdo->query('SELECT UUID()')->fetchColumn();
  $title = 'Hinweis zur Kautionsrückzahlung';
  $content = <<<TXT
Die Rückzahlung der vom Mieter geleisteten Kaution erfolgt nach ordnungsgemäßer Rückgabe der Wohnung und Prüfung des Mietobjekts durch den Vermieter. Der Vermieter ist berechtigt, die Kaution bis zu sechs Monate nach Beendigung des Mietverhältnisses zurückzubehalten, soweit dies zur Feststellung und Durchsetzung möglicher Schadensersatzansprüche oder Nachforderungen aus dem Mietverhältnis erforderlich ist (vgl. BGH, Urteil vom 18.01.2006 – VIII ZR 71/05). Eine darüber hinausgehende teilweise Einbehaltung ist zulässig, soweit noch nicht über die Nebenkosten abgerechnet wurde und mit einer Nachforderung zu rechnen ist.
Der Mieter hat zu diesem Zweck seine aktuelle Bankverbindung anzugeben. Die Auszahlung der Kaution erfolgt auf das vom Mieter benannte Konto, soweit keine Gegenansprüche des Vermieters bestehen.
TXT;
  $ins=$pdo->prepare("INSERT INTO legal_texts (id,name,version,title,content,created_at) VALUES (?,?,?,?,?,NOW())");
  $ins->execute([$id,'kaution_hinweis',1,$title,$content]);
  echo "Seeded kaution_hinweis v1\n";
} else {
  echo "Skip (kaution_hinweis bereits vorhanden)\n";
}
