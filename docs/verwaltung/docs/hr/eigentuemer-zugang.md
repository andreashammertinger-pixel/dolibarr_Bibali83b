# Pristup za vlasnike

Vlasnici se mogu prijaviti u Dolibarr i ondje vide **samo svoj apartman**: zaduženja, uplate, objašnjenja i obračunske listove.

## Vlasnik kao kontakt

Na apartmanu (*Apartmani i kontakti*) → *Kontakti*:

- **Vlasnik = Da** → prima zaduženja i obračune, navodi se imenom i OIB-om na dokumentima.
- Najmoprimce, kontakte za hitne slučajeve itd. ostaviti na *Ne*.
- Upisati **OIB** (11 znamenki, provjerava se) i **e-poštu**.
- Tvrtka kao vlasnik: naziv tvrtke kao prezime, ime prazno.

## Izrada korisnika

1. *Korisnici i grupe → Novi korisnik*, **vanjski** – povezan s apartmanom (poslovnim partnerom).
2. Dodati u grupu **Bibali83B**.

Prava grupe u tvrtki *Bibali 83B*:

| Područje | Pravo |
|---|---|
| Računi | **pregled izlaznih računa** |
| Poslovni partneri | **pregled poslovnih partnera** |
| Kontakti | isključeno |
| **Upravljanje zgradom** | **sve isključeno** |

!!! danger "Vlasnici ne smiju imati prava za upravljanje zgradom"
    Modul sadrži podatke **svih** apartmana. Za vanjske korisnike je zaključan; *Pregled* prikazuje crveno upozorenje ako su prava ipak dodijeljena.

## Što vlasnik vidi

- *Zaduženja | Plaćanja → Zaduženja*: svoja zaduženja s PDF-om (izvornik, kopija, objašnjenje)
- apartman → *Povezane datoteke*: sve što mu je poslano (zaduženja, objašnjenja, obračuni) s datumom slanja
- upute za vlasnike: [Pomoć za vlasnike](../../../eigentuemer/)
