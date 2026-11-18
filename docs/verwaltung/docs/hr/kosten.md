# Troškovi i ulazni računi

## Plan troškova

*Upravljanje zgradom → Plan troškova*

Svaka **vrsta troška** ima:

| Polje | Značenje |
|---|---|
| Naziv | ispisuje se u objašnjenju i obračunu; moguć prijevod po jeziku |
| Zgrada | jedna kuća ili **sve zgrade** |
| Iznos neto po razdoblju + PDV | npr. godišnja premija osiguranja |
| Razdoblje (mjeseci) | 12 = godišnji iznos, 6 = polugodišnje, 1 = mjesečno |
| Ključ raspodjele | m² (standard), suvlasnički udio, jednako po jedinici |
| aktivno | samo aktivne vrste troška ulaze u zaduženje |

Mjesečni iznos je **iznos × (1 + PDV) ÷ mjeseci**. Stopa zajedničkih troškova po apartmanu je zbroj njegovih udjela ÷ m², zaokruženo na 2 decimale.

!!! example "Primjer"
    Održavanje vrta Kuća 3: 150 € neto + 25 % PDV za **6 mjeseci** → 31,25 € mjesečno → samo Kuća 3.

## Ulazni računi

Račune dobavljača unositi uobičajeno u Dolibarr (*Nabava → Ulazni računi*) i dodatno:

- odabrati **Vrstu troška (upravljanje)** → određuje raspodjelu u godišnjem obračunu,
- označiti **Plaćeno iz pričuve** kod održavanja iz pričuve → iznos se prenosi s računa pričuve i **ne** ulazi u obračun zajedničkih troškova.

## Mobilni unos

*Upravljanje zgradom → Mobilni unos* (na mobitelu): dobavljač, vrsta troška, iznos, fotografija računa – gotovo. Račun se sprema kao nacrt.

## Naknada upravitelja

*Izradi zaduženja → Kreiraj ulazni račun upravitelja* izrađuje račun upravitelja zajednici (0,30 €/m² neto + PDV).
