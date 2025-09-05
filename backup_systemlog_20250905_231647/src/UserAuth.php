<?php
declare(strict_types=1);

namespace App;

use App\Database;
use PDO;

/**
 * Erweiterte Autorisierungs- und Benutzerverwaltungsklasse
 */
final class UserAuth
{
    /**
     * Prüft ob der aktuelle Benutzer Admin ist
     */
    public static function isAdmin(): bool
    {
        $user = Auth::user();
        return $user && ($user['role'] ?? '') === 'admin';
    }

    /**
     * Prüft ob der aktuelle Benutzer Hausverwaltung ist
     */
    public static function isHausverwaltung(): bool
    {
        $user = Auth::user();
        return $user && ($user['role'] ?? '') === 'hausverwaltung';
    }

    /**
     * Prüft ob der aktuelle Benutzer Eigentümer ist
     */
    public static function isEigentuemer(): bool
    {
        $user = Auth::user();
        return $user && ($user['role'] ?? '') === 'eigentuemer';
    }

    /**
     * Erzwingt Admin-Berechtigung
     */
    public static function requireAdmin(): void
    {
        Auth::requireAuth();
        if (!self::isAdmin()) {
            Flash::add('error', 'Zugriff verweigert. Administrator-Berechtigung erforderlich.');
            header('Location: /protocols');
            exit;
        }
    }

    /**
     * Erzwingt Admin oder Hausverwaltung-Berechtigung
     */
    public static function requireAdminOrHausverwaltung(): void
    {
        Auth::requireAuth();
        if (!self::isAdmin() && !self::isHausverwaltung()) {
            Flash::add('error', 'Zugriff verweigert. Administrator- oder Hausverwaltungs-Berechtigung erforderlich.');
            header('Location: /protocols');
            exit;
        }
    }

    /**
     * Holt die zugewiesenen Manager-IDs für einen Hausverwaltungs-Benutzer
     */
    public static function getAssignedManagerIds(?string $userId = null): array
    {
        if (!$userId) {
            $user = Auth::user();
            $userId = $user['id'] ?? '';
        }

        if (!$userId) {
            return [];
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT manager_id FROM user_manager_assignments WHERE user_id = ?');
        $stmt->execute([$userId]);
        
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Holt die zugewiesenen Owner-IDs für einen Eigentümer-Benutzer
     */
    public static function getAssignedOwnerIds(?string $userId = null): array
    {
        if (!$userId) {
            $user = Auth::user();
            $userId = $user['id'] ?? '';
        }

        if (!$userId) {
            return [];
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT owner_id FROM user_owner_assignments WHERE user_id = ?');
        $stmt->execute([$userId]);
        
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Setzt Manager-Zuweisungen für einen Benutzer
     */
    public static function setManagerAssignments(string $userId, array $managerIds): void
    {
        $pdo = Database::pdo();
        
        // Lösche bestehende Zuweisungen
        $stmt = $pdo->prepare('DELETE FROM user_manager_assignments WHERE user_id = ?');
        $stmt->execute([$userId]);
        
        // Füge neue Zuweisungen hinzu
        if (!empty($managerIds)) {
            $stmt = $pdo->prepare('INSERT INTO user_manager_assignments (id, user_id, manager_id) VALUES (UUID(), ?, ?)');
            foreach ($managerIds as $managerId) {
                $stmt->execute([$userId, $managerId]);
            }
        }
    }

    /**
     * Setzt Owner-Zuweisungen für einen Benutzer
     */
    public static function setOwnerAssignments(string $userId, array $ownerIds): void
    {
        $pdo = Database::pdo();
        
        // Lösche bestehende Zuweisungen
        $stmt = $pdo->prepare('DELETE FROM user_owner_assignments WHERE user_id = ?');
        $stmt->execute([$userId]);
        
        // Füge neue Zuweisungen hinzu
        if (!empty($ownerIds)) {
            $stmt = $pdo->prepare('INSERT INTO user_owner_assignments (id, user_id, owner_id) VALUES (UUID(), ?, ?)');
            foreach ($ownerIds as $ownerId) {
                $stmt->execute([$userId, $ownerId]);
            }
        }
    }

    /**
     * Prüft ob ein Benutzer Zugriff auf ein bestimmtes Protokoll hat
     */
    public static function canAccessProtocol(string $protocolId): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        // Admin kann alles
        if (self::isAdmin()) {
            return true;
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT manager_id, owner_id FROM protocols WHERE id = ?');
        $stmt->execute([$protocolId]);
        $protocol = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$protocol) {
            return false;
        }

        // Hausverwaltung kann nur Protokolle ihrer zugewiesenen Manager einsehen
        if (self::isHausverwaltung()) {
            if (!$protocol['manager_id']) {
                return false;
            }
            $assignedManagerIds = self::getAssignedManagerIds();
            return in_array($protocol['manager_id'], $assignedManagerIds);
        }

        // Eigentümer kann nur Protokolle ihrer zugewiesenen Eigentümer einsehen
        if (self::isEigentuemer()) {
            if (!$protocol['owner_id']) {
                return false;
            }
            $assignedOwnerIds = self::getAssignedOwnerIds();
            return in_array($protocol['owner_id'], $assignedOwnerIds);
        }

        return false;
    }

    /**
     * Filtert eine Protokoll-Query basierend auf Benutzerberechtigungen
     */
    public static function addProtocolAccessFilter(string $query, array &$params = []): string
    {
        $user = Auth::user();
        if (!$user) {
            // Kein Benutzer -> keine Protokolle
            return $query . ' AND 1=0';
        }

        // Admin kann alles sehen
        if (self::isAdmin()) {
            return $query;
        }

        // Hausverwaltung: nur Protokolle zugewiesener Manager
        if (self::isHausverwaltung()) {
            $managerIds = self::getAssignedManagerIds();
            if (empty($managerIds)) {
                return $query . ' AND 1=0';
            }
            $placeholders = str_repeat('?,', count($managerIds) - 1) . '?';
            $params = array_merge($params, $managerIds);
            return $query . ' AND p.manager_id IN (' . $placeholders . ')';
        }

        // Eigentümer: nur Protokolle zugewiesener Eigentümer
        if (self::isEigentuemer()) {
            $ownerIds = self::getAssignedOwnerIds();
            if (empty($ownerIds)) {
                return $query . ' AND 1=0';
            }
            $placeholders = str_repeat('?,', count($ownerIds) - 1) . '?';
            $params = array_merge($params, $ownerIds);
            return $query . ' AND p.owner_id IN (' . $placeholders . ')';
        }

        // Fallback: kein Zugriff
        return $query . ' AND 1=0';
    }

    /**
     * Holt verfügbare Manager für einen Benutzer (für Dropdown-Listen)
     */
    public static function getAvailableManagers(): array
    {
        $pdo = Database::pdo();
        
        // Admin kann alle Manager sehen
        if (self::isAdmin()) {
            $stmt = $pdo->query('SELECT id, name, company FROM managers ORDER BY name');
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Hausverwaltung kann nur zugewiesene Manager sehen
        if (self::isHausverwaltung()) {
            $managerIds = self::getAssignedManagerIds();
            if (empty($managerIds)) {
                return [];
            }
            $placeholders = str_repeat('?,', count($managerIds) - 1) . '?';
            $stmt = $pdo->prepare('SELECT id, name, company FROM managers WHERE id IN (' . $placeholders . ') ORDER BY name');
            $stmt->execute($managerIds);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Eigentümer können keine Manager sehen
        return [];
    }

    /**
     * Holt verfügbare Owner für einen Benutzer (für Dropdown-Listen)
     */
    public static function getAvailableOwners(): array
    {
        $pdo = Database::pdo();
        
        // Admin kann alle Owner sehen
        if (self::isAdmin()) {
            $stmt = $pdo->query('SELECT id, name, company FROM owners ORDER BY name');
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Eigentümer kann nur zugewiesene Owner sehen
        if (self::isEigentuemer()) {
            $ownerIds = self::getAssignedOwnerIds();
            if (empty($ownerIds)) {
                return [];
            }
            $placeholders = str_repeat('?,', count($ownerIds) - 1) . '?';
            $stmt = $pdo->prepare('SELECT id, name, company FROM owners WHERE id IN (' . $placeholders . ') ORDER BY name');
            $stmt->execute($ownerIds);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Hausverwaltung können keine Owner sehen/zuweisen
        return [];
    }

    /**
     * Prüft ob ein Benutzer ein bestimmtes Protokoll erstellen darf
     */
    public static function canCreateProtocol(array $protocolData): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        // Admin kann alles
        if (self::isAdmin()) {
            return true;
        }

        $managerId = $protocolData['manager_id'] ?? '';
        $ownerId = $protocolData['owner_id'] ?? '';

        // Hausverwaltung kann nur Protokolle für zugewiesene Manager erstellen
        if (self::isHausverwaltung()) {
            if (!$managerId) {
                return false;
            }
            $assignedManagerIds = self::getAssignedManagerIds();
            return in_array($managerId, $assignedManagerIds);
        }

        // Eigentümer kann nur Protokolle für zugewiesene Eigentümer erstellen
        if (self::isEigentuemer()) {
            if (!$ownerId) {
                return false;
            }
            $assignedOwnerIds = self::getAssignedOwnerIds();
            return in_array($ownerId, $assignedOwnerIds);
        }

        return false;
    }

    /**
     * Holt alle verfügbaren Manager für Admin (für Zuweisungen)
     */
    public static function getAllManagers(): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->query('SELECT id, name, company FROM managers ORDER BY name');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Holt alle verfügbaren Owner für Admin (für Zuweisungen)
     */
    public static function getAllOwners(): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->query('SELECT id, name, company FROM owners ORDER BY name');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
