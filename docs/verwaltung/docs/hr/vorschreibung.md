# Izrada i slanje zaduženja

Zaduženje je račun zajednice apartmanu. Sadrži tri stavke: **zajedničke troškove**, **pričuvu** kuće i **upravljanje** – svaka kao stopa u €/m² × stambena površina.

## 1. Provjera pregleda

*Upravljanje zgradom → Izradi zaduženja*

1. Odabrati razdoblje:
    - **godišnje zaduženje:** *Od* `2027-01`, *do* `2027-12` (uobičajeno, u prosincu za sljedeću godinu)
    - **ostatak godine:** gumb *Ostatak godine* (npr. početak tijekom godine)
    - **jedan mjesec:** gumb *Tekući mjesec*
2. Kliknuti **Osvježi pregled** i provjeriti tablicu: iznos po apartmanu, upozorenja (nedostaje OIB, nema kontakta vlasnika, nedostaje KPD).
3. U stupcu **PDF pregled** otvoriti pojedine PDF-ove (izvornik i prijevod, s vodenim žigom *Nacrt*).

!!! info "Mjerodavan je hrvatski izvornik (porezna uprava)"
    Prijevod zaduženja gore lijevo ima okvir **„KOPIJA – Prijevod“** s rečenicom *„Ovaj prijevod služi samo za informaciju. Pravno je mjerodavan hrvatski izvornik.“* (na jeziku vlasnika i na hrvatskom). To vrijedi samo za zaduženja/račune; svi ostali dokumenti imaju samo AI napomenu u podnožju.

## 2. Izrada nacrta

1. Kvačicu **„Odmah potvrdi račune“ ne stavljati** – nastaju nacrti koje je moguće obrisati.
2. Kliknuti **Izradi zaduženja**.

!!! tip "Potreban ispravak?"
    Nacrt obrisati u popisu računa, ispraviti plan troškova odnosno apartman i ponovo *Izradi zaduženja*. Već postojeća zaduženja za isto razdoblje **ne** izrađuju se dvaput.

## 3. Ovjera

*Zaduženja | Plaćanja → Zaduženja → Popis*

1. Filtar *Status* = **Skica**, označiti sve.
2. **Grupne radnje → Ovjeri**.

Računi dobivaju broj (npr. `IN2611-0002`) i PDF (hrvatski + jezik vlasnika).

!!! info "Ovjera nakon dana dospijeća"
    Ako je rata pri ovjeri već dospjela (npr. ovjera 18., dospijeće 5.), automatski vrijedi rok od **7 dana od ovjere**. Sljedeće rate ostaju na dan dospijeća.

## 4. Slanje

*Upravljanje zgradom → Slanje zaduženja*

1. **Obračunski mjesec** = prvi mjesec razdoblja (npr. `2027-01`), *OK*.
2. Još neposlana zaduženja su označena.
3. **Testno slanje**: upisati vlastitu adresu → *Testno slanje (samo na ovu adresu)*. Poruke dolaze s `[TEST]` i svim privicima.
4. **Pošalji vlasnicima** → potvrditi.

Svaka poruka sadrži:

- zaduženje na hrvatskom i na jeziku vlasnika,
- **objašnjenje zaduženja** (sastav, stope po kući, pričuva, postupak) – izrađuje se pri slanju,
- tekst na jeziku apartmana s mjesečnom ratom, dospijećem i podacima za uplatu.

!!! danger "Ne preko „Pošalji e-poštu“ na računu"
    Opći prozor za e-poštu u Dolibarru (na računu) prilaže samo hrvatski PDF i standardni tekst. Uvijek koristiti *Upravljanje zgradom → Slanje zaduženja*.

## Nakon slanja

- **Povratne poruke** (pogrešna adresa) stižu na adresu pošiljatelja – provjeriti pretinac.
- Sve poslane datoteke dodatno se nalaze kod **apartmana → Povezane datoteke**, s datumom slanja na početku naziva (npr. `2026-11-18_IN2611-0001.pdf`). Testno slanje ništa ne sprema.
- Vlasnici vide zaduženje i objašnjenje i u svom [pristupu](eigentuemer-zugang.md).
- Uplate: vidi [Uplate](zahlungen.md).
