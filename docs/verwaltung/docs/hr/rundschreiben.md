# Okružnice vlasnicima

*Upravljanje zgradom → Okružnice* – e-pošta svim ili odabranim vlasnicima, svakome na njegovom jeziku, s prilozima.

## Postupak

1. **Nova okružnica**.
2. **Tekst**: *napisano na* (Vaš jezik), predmet i sadržaj. Pozdrav i završnu formulu Dolibarr dodaje automatski – u tekstu ih izostavite. *Spremi* prevodi na jezike odabranih primatelja; pri kasnijim promjenama Dolibarr pita treba li preuzeti promjenu na ostalim jezicima.
3. **Prijevode** provjerite i po potrebi ispravite (oznaka *KI* odnosno *uređeno*). *Prevedi sada (AI)* ponovo prevodi sve.
4. **Prilozi**:
    - **Prijenos datoteke** s jezikom – ili *svi jezici* za jezično neutralne datoteke (nacrt, fotografija, tablica).
    - **Datoteka iz Dolibarra** (modul *Dokumenti*).
    - **Isti dopis na više jezika** (npr. DE, HR, SL): prvo jedan jezik kao *Prenesi novi dokument*, ostale jezike zatim **kod dokumenta** s *Dodaj jezičnu verziju*. Samo nedostajući jezici prevode se pomoću AI; prava jezična verzija zamjenjuje postojeći AI prijevod.
    - Već preneseno zasebno? Kod dokumenta *Isti dopis kao … → Spoji*. Stranica upozorava ako postoji više dokumenata sa samo po jednim jezikom.
    - **Prevedi nedostajuće jezične verzije pomoću AI (PDF)**: za svaki jezik izrađuje PDF; dolje sitno piše „Pomoću AI prevedeno na odgovarajući jezik – u slučaju nejasnoća molimo javite se.“
    - **Dokument upravitelja** – odaberite *Plan pričuve*, *Obračunski list (po apartmanu)* s godinom ili *Zaduženje s objašnjenjem (po apartmanu)* s obračunskim mjesecom, *Dodaj*. Ti se dokumenti **izrađuju iznova pri slanju** (trenutno stanje), za svaki apartman s njegovim brojkama, kao hrvatski izvornik i na jeziku vlasnika. Ako za neki apartman dokument ne postoji (npr. nema zaduženja za taj mjesec), taj se apartman **ne** šalje i u zapisniku se prikazuje kao pogreška.
    - **Prilozi samo na jeziku vlasnika** (zadano: uključeno): svaki vlasnik dobiva dopise, obavijesti i plan pričuve samo na svom jeziku – bez dodatne hrvatske datoteke (manje podataka). Ako nema verzije na njegovom jeziku, dobiva izvornik. **Zaduženje s objašnjenjem i obračunski list uvijek se šalju kao hrvatski izvornik i na jeziku vlasnika** (porezna uprava). Isključite ako uz okružnicu treba poslati i hrvatsku verziju.
    - Tablica *bit će priloženo* prikazuje po jeziku što svaki vlasnik dobiva: **izvornik** (hrvatsku verziju, inače prenesenu izvornu verziju) i **verziju na svom jeziku**.
5. **Primatelji**: svi, nijedan ili po kući (H1, H2 …), *Spremi*. Apartmani bez e-pošte vlasnika su zaključani.
6. **Slanje**:
    - **Testno slanje** – po jedna poruka za svaki jezik primatelja na Vašu adresu, s točno onim tekstom i prilozima koje bi dobio primjerni apartman tog jezika (na vrhu testa piše koji apartman i koji primatelji).
    - **Pošalji odabranim vlasnicima** – po jedna poruka po apartmanu njegovim vlasnicima.

!!! info "Slanje korak po korak"
    Test, slanje i *Prevedi nedostajuće jezične verzije pomoću AI* odvijaju se u pojedinačnim koracima s prikazom napretka (prijevod teksta, AI prijevod po datoteci i jeziku, testna poruka po jeziku, po jedna poruka po apartmanu). Ostavite stranicu otvorenom dok se sama ne osvježi. Ako poslužitelju treba dulje (npr. kod AI prijevoda), piše *uskoro ponovna provjera* – korak se nastavlja u pozadini. Ako se postupak prekine: ponovno učitajte stranicu i pokrenite ponovo – obavljeno se ne ponavlja, **već poslani apartmani nikada ne dobivaju okružnicu dvaput**.

!!! warning "Test je obavezan"
    *Slanje* je moguće tek nakon uspješnog testa (zelena napomena *Test uspješno poslan …*). Svaka promjena teksta, prijevoda, priloga ili primatelja zahtijeva novi test.

Nedostajući prijevodi automatski se dodaju prije slanja. Zapisnik slanja nalazi se dolje; tekst i prilozi spremaju se kod **apartmana → Povezane datoteke** (s datumom slanja).

## Sljedivost kod apartmana

Svaka poslana poruka vlasnicima nalazi se kod **apartmana → Događaji/Agenda** – kronološki na jednom popisu: zaduženja, obračunski listovi i okružnice. Događaj sadrži primatelje, predmet, tekst na jeziku vlasnika i popis priloga; same datoteke nalaze se s datumom slanja pod **Povezane datoteke**. Za ranije poslane okružnice događaji se dodaju pri aktiviranju modula. Odgovori vlasnika bit će dodani čim se postavi Dolibarr *sakupljač e-pošte*.

!!! info "Napomena u svakoj poruci"
    Ispod završne formule sitno piše: Upravljanje zgradom (verzija, vrijeme) · „Pomoću AI prevedeno na odgovarajući jezik – u slučaju nejasnoća molimo javite se.“ Ista rečenica stoji u podnožju svih dokumenata – na svakom jeziku jednako, i na hrvatskom.

!!! warning "Bez osobnih podataka u prilozima za AI prijevod"
    Za AI prijevod PDF se šalje usluzi prevođenja. Dokumente s imenima, OIB-om ili bankovnim podacima pojedinih vlasnika ne prevodite pomoću AI.
