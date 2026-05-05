<?php
/**
 * Page de réglages du module Bénévoles.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Modules\Benevoles\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Page de réglages — actuellement réduite à ses constantes d'option.
 *
 * L'écran complet d'édition (champs : courriel de contact, expéditeur,
 * blocs d'introduction de profil par rôle…) est implémenté à la phase 5.
 * Les services métier référencent dès maintenant `OPTION_NAME` afin que
 * la migration de la phase 5 se résume à enrichir cette classe.
 */
final class SettingsPage {

	public const OPTION_NAME = 'jde_plugin_benevoles_settings';
}
