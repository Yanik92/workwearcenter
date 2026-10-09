<?php
declare(strict_types=1);

/*
 * Zentrale Standardtexte der öffentlichen Website (Fassung 10.3).
 * Gespeicherte Texte aus Admin → Inhalte haben immer Vorrang; diese Werte gelten,
 * solange ein Feld nicht gepflegt ist, und können per Knopfdruck übernommen werden.
 */
function yw_content_defaults(): array {
    return [
        // Startseite – Einstieg
        'hero_eyebrow'=>'Unternehmensshops für Firmenkleidung',
        'hero_title'=>'Firmenkleidung, die Ihr Team selbst bestellt.',
        'hero_text'=>'Ihr eigener Online-Shop für Arbeits- und Firmenkleidung: Mitarbeitende wählen Größe und Menge, Sie behalten Budgets und Freigaben im Blick – und wir veredeln und liefern aus Salzkotten.',
        'hero_button_text'=>'Unternehmensshop anfragen',
        'hero_button_link'=>'/anfrage',
        'hero_secondary_text'=>'Mustershop ansehen',
        'hero_secondary_link'=>'/mustershop',
        // Startseite – Veredelung und Bausteine
        'intro_title'=>'Digital bestellt. Von Hand veredelt.',
        'intro_text'=>'Hinter dem WorkwearCenter steht keine anonyme Plattform, sondern die Produktion von Yanik Textildruck in Salzkotten. Wir sticken, drucken und prüfen jedes Teil selbst – und beraten Sie, wie Ihr Logo auf Stoff am besten wirkt.',
        'section1_title'=>'Ihre Firmenkollektion',
        'section1_text'=>'Nur die mit Ihnen abgestimmten Artikel, Farben und Veredelungen sind bestellbar. So bleibt Ihr Auftritt einheitlich – auch bei jeder Nachbestellung.',
        'section2_title'=>'Persönliche Zugänge',
        'section2_text'=>'Jede Person bestellt mit eigenem Konto. Rollen, Abteilungen und Kostenstellen sind hinterlegt, neue Kolleginnen und Kollegen in Minuten eingerichtet.',
        'section3_title'=>'Budgets & Freigaben',
        'section3_text'=>'Jahresbudgets und Stücklimits werden bei jeder Bestellung automatisch geprüft. Auf Wunsch gibt eine verantwortliche Person frei, bevor produziert wird.',
        // Startseite – Ablauf
        'process_title'=>'In vier Schritten zum eigenen Firmenshop.',
        'process_step1_title'=>'Kollektion abstimmen',
        'process_step1_text'=>'Wir wählen mit Ihnen Artikel, Farben, Logo-Positionen und Veredelung aus – auf Wunsch mit Mustern zum Anfassen.',
        'process_step2_title'=>'Shop einrichten',
        'process_step2_text'=>'Wir richten Ihren Shop mit Logo, Budgets, Regeln und Zugängen ein. Sie müssen nichts installieren.',
        'process_step3_title'=>'Team bestellt',
        'process_step3_text'=>'Mitarbeitende bestellen selbst in ihrer Größe, Verantwortliche geben mit einem Klick frei.',
        'process_step4_title'=>'Wir veredeln & liefern',
        'process_step4_text'=>'Wir beschaffen, veredeln und prüfen jedes Teil und liefern an Ihre Adresse – abgerechnet wie gewohnt per Rechnung.',
        // Startseite – Häufige Fragen
        'faq1_question'=>'Für welche Unternehmen lohnt sich ein eigener Shop?',
        'faq1_answer'=>'Für alle, die regelmäßig einheitliche Arbeits- oder Firmenkleidung brauchen – vom Handwerksbetrieb mit zehn Mitarbeitenden bis zum Unternehmen mit mehreren Standorten und Abteilungen.',
        'faq2_question'=>'Können wir Budgets und Stückzahlen begrenzen?',
        'faq2_answer'=>'Ja. Sie legen ein Jahresbudget pro Person fest – auf Wunsch individuell – und begrenzen einzelne Artikel auf feste Stückzahlen. Der Shop prüft das bei jeder Bestellung automatisch.',
        'faq3_question'=>'Wer gibt Bestellungen frei?',
        'faq3_answer'=>'Das entscheiden Sie: Entweder prüft eine verantwortliche Person jede Bestellung, oder Bestellungen innerhalb des Budgets gehen direkt in die Produktion.',
        'faq4_question'=>'Wie wird abgerechnet?',
        'faq4_answer'=>'Sie erhalten wie gewohnt eine Rechnung von Yanik Textildruck. Falls Ihre Mitarbeitenden einen Eigenanteil tragen, wird er im Shop transparent ausgewiesen.',
        // Startseite – Abschluss
        'final_cta_title'=>'Lassen Sie uns Ihren Firmenshop aufsetzen.',
        'final_cta_text'=>'Unverbindlich und persönlich: Wir besprechen Sortiment, Veredelung und Ablauf – und zeigen Ihnen am Mustershop, wie Ihr eigener Shop aussehen kann.',
        'final_cta_button'=>'Unverbindlich anfragen',
        'final_cta_link'=>'/anfrage',
        // Unterseiten
        'services_title'=>'Leistungen',
        'services_intro'=>'Arbeitskleidung, Veredelung und Ihr eigener Firmenshop – alles aus einer Hand.',
        'services_body'=>'Wir statten Unternehmen mit passender Arbeits- und Firmenkleidung aus, veredeln sie in unserer eigenen Produktion in Salzkotten und organisieren Nachbestellungen über Ihren persönlichen Unternehmensshop. Sie haben einen festen Ansprechpartner – vom ersten Muster bis zur Lieferung.',
        'about_title'=>'Über uns',
        'about_intro'=>'Yanik Textildruck aus Salzkotten: Textilveredelung mit Leidenschaft – und ein digitaler Bestellweg für Ihr Team.',
        'about_body'=>'Wir sind ein Textilveredelungsbetrieb aus Salzkotten und sticken und bedrucken Kleidung für Unternehmen, Vereine und Teams. Aus der täglichen Arbeit mit unseren Firmenkunden ist das WorkwearCenter entstanden: ein Weg, wie Mitarbeitende ihre Firmenkleidung selbst bestellen können – ohne dass jemand Listen führen, Größen einsammeln oder Budgets nachrechnen muss.',
        'how_title'=>'So funktioniert’s',
        'how_intro'=>'Vom ersten Gespräch bis zur fertigen Bestellung – so läuft Ihr Unternehmensshop ab.',
        'how_body'=>'Sie legen einmal fest, was Ihr Team tragen soll und welche Regeln gelten. Danach bestellen Ihre Mitarbeitenden selbst – und wir kümmern uns um Beschaffung, Veredelung und Lieferung.',
        'contact_title'=>'Kontakt',
        'contact_intro'=>'Sie möchten einen Unternehmensshop einrichten oder haben eine Frage zu Ihrer Firmenkleidung? Wir sind persönlich für Sie da.',
        'contact_body'=>'Schreiben Sie uns gern eine E-Mail. Für eine Shop-Anfrage nutzen Sie am besten das Anfrageformular – dann liegen uns alle wichtigen Eckdaten direkt vor und wir können uns gut vorbereitet bei Ihnen melden.',
        'footer_text'=>'WorkwearCenter – ein Service von Yanik Textildruck, Salzkotten',
    ];
}

/** Gespeicherter Text oder Standardtext der aktuellen Fassung. */
function yw_text(string $key): string {
    $defaults=yw_content_defaults();
    $value=current_site_setting($key,'');
    return trim($value)!=='' ? $value : (string)($defaults[$key]??'');
}

function yw_content_apply_defaults(): int {
    $defaults=yw_content_defaults();
    $backup=[];
    foreach(array_keys($defaults) as $key){$backup[$key]=current_site_setting($key,'');}
    db()->beginTransaction();
    try{
        set_site_setting('content_backup_json',json_encode(['saved_at'=>date('Y-m-d H:i:s'),'values'=>$backup],JSON_UNESCAPED_UNICODE));
        foreach($defaults as $key=>$value)set_site_setting($key,$value);
        db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
    return count($defaults);
}

function yw_content_restore_backup(): int {
    $backup=json_decode(current_site_setting('content_backup_json',''),true);
    if(!is_array($backup) || !is_array($backup['values']??null))throw new RuntimeException('Es gibt keine gesicherte Textfassung.');
    $allowed=yw_content_defaults();$count=0;
    db()->beginTransaction();
    try{
        foreach($backup['values'] as $key=>$value){if(isset($allowed[$key]) && is_string($value)){set_site_setting($key,$value);$count++;}}
        db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
    return $count;
}
