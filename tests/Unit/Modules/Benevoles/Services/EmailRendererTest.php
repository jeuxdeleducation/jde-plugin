<?php
/**
 * Tests unitaires de l'EmailRenderer.
 *
 * @package JDE
 */

declare(strict_types=1);

namespace JDE\Tests\Unit\Modules\Benevoles\Services;

use JDE\Modules\Benevoles\Services\EmailRenderer;
use PHPUnit\Framework\TestCase;

final class EmailRendererTest extends TestCase {

	private EmailRenderer $renderer;

	protected function setUp(): void {
		parent::setUp();
		$this->renderer = new EmailRenderer();
	}

	public function testReplacesSimpleVariableEscaped(): void {
		$out = $this->renderer->render( 'Bonjour {{prenom}}.', array( 'prenom' => 'Léa' ) );
		$this->assertSame( 'Bonjour Léa.', $out );
	}

	public function testEscapesHtmlSpecialChars(): void {
		$out = $this->renderer->render( '{{nom}}', array( 'nom' => '<script>x</script>' ) );
		$this->assertSame( '&lt;script&gt;x&lt;/script&gt;', $out );
	}

	public function testRawVariableIsNotEscaped(): void {
		$out = $this->renderer->render( '{{!message}}', array( 'message' => '<p>Bienvenue</p>' ) );
		$this->assertSame( '<p>Bienvenue</p>', $out );
	}

	public function testMissingVariableRendersAsEmptyString(): void {
		$out = $this->renderer->render( 'a{{absent}}b', array() );
		$this->assertSame( 'ab', $out );
	}

	public function testPositiveSectionRendersWhenValuePresent(): void {
		$tpl = 'Avant{{#info}} info: {{info}}{{/info}}fin';
		$out = $this->renderer->render( $tpl, array( 'info' => 'OK' ) );
		$this->assertSame( 'Avant info: OKfin', $out );
	}

	public function testPositiveSectionIsHiddenWhenValueEmpty(): void {
		$tpl = 'Avant{{#info}} secret{{/info}}fin';
		$out = $this->renderer->render( $tpl, array( 'info' => '' ) );
		$this->assertSame( 'Avantfin', $out );
	}

	public function testPositiveSectionIsHiddenWhenValueAbsent(): void {
		$tpl = 'Avant{{#info}} secret{{/info}}fin';
		$out = $this->renderer->render( $tpl, array() );
		$this->assertSame( 'Avantfin', $out );
	}

	public function testNegativeSectionRendersWhenValueAbsent(): void {
		$tpl = '{{^info}}aucune info{{/info}}';
		$out = $this->renderer->render( $tpl, array() );
		$this->assertSame( 'aucune info', $out );
	}

	public function testNegativeSectionIsHiddenWhenValuePresent(): void {
		$tpl = '{{^info}}aucune info{{/info}}';
		$out = $this->renderer->render( $tpl, array( 'info' => 'truc' ) );
		$this->assertSame( '', $out );
	}

	public function testCombinationOfSectionsAndVariables(): void {
		$tpl = 'Bonjour {{prenom}}.{{#poste}} Tu es affecté à {{poste}}.{{/poste}}{{^poste}} Aucune affectation.{{/poste}}';

		$avec = $this->renderer->render(
			$tpl,
			array(
				'prenom' => 'Sam',
				'poste'  => 'accueil',
			)
		);
		$this->assertSame( 'Bonjour Sam. Tu es affecté à accueil.', $avec );

		$sans = $this->renderer->render( $tpl, array( 'prenom' => 'Sam' ) );
		$this->assertSame( 'Bonjour Sam. Aucune affectation.', $sans );
	}

	public function testZeroIsTreatedAsEmptyForSections(): void {
		$tpl = '{{#nb}}positif{{/nb}}';
		$this->assertSame( '', $this->renderer->render( $tpl, array( 'nb' => 0 ) ) );
		$this->assertSame( 'positif', $this->renderer->render( $tpl, array( 'nb' => 1 ) ) );
	}
}
