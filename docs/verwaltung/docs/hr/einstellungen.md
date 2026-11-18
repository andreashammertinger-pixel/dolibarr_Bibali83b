# Postavke i novi objekt

## Postavke

*Postavke upravljanja zgradom* (zupčanik kod modula):

| Postavka | Namjena |
|---|---|
| Bankovni račun, način plaćanja, dan dospijeća | podaci na zaduženju |
| PDV zajedničkih troškova / upravljanja | zaduženje bez PDV-a (zajednica), upravljanje s 25 % |
| Upravitelj | tvrtka koja upravlja (navodi se na svim dokumentima) |
| Pošiljatelj / BCC | adresa pošiljatelja, kopija svakog zaduženja |
| Napomena o digitalnom dokumentu | „Digitalni dokument – papirnata verzija se ne dostavlja“ |
| Poslane dokumente spremi kod apartmana | kopija svake poslane datoteke pod *Povezane datoteke* apartmana |
| Priloži objašnjenje | slanje objašnjenja zaduženja |
| Adresa pristupa za vlasnike | navodi se u objašnjenju |
| Adresa pomoći | osnovna adresa ovog priručnika |
| Rast troškova gradnje / razdoblje planiranja | za plan pričuve |

## Novi objekt

Modul se može koristiti i za druge zgrade/zajednice – za svaku zajednicu posebna tvrtka (Multicompany).

1. Kreirati tvrtku (OIB u Prof-ID 1, matični broj u Prof-ID 2), aktivirati modul.
2. Apartmani kao poslovni partneri (naziv završava brojem, npr. „… Apt 1.1“), polja *stambena površina m²*, jezik.
3. Vlasnici kao kontakti (vidi [Pristup za vlasnike](eigentuemer-zugang.md)).
4. Bankovni računi zajednice i za svaku kuću račun pričuve.
5. *Postavke → Uvoz početnih podataka* s predloškom `vorlage_startdaten.json` (zgrade, jedinice, plan troškova).
6. Uvesti plan pričuve (`vorlage_ruecklagenplan.json`).
7. Izraditi [zaduženje](vorschreibung.md).
