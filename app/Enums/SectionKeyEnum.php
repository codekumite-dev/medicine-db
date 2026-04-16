<?php

namespace App\Enums;

enum SectionKeyEnum: string
{
    case Overview = 'overview';
    case Usage = 'usage';
    case AlternateNames = 'alternate_names';
    case HowItWorks = 'how_it_works';
    case Dosage = 'dosage';
    case StandardDosage = 'standard_dosage';
    case ClinicalUseCases = 'clinical_use_cases';
    case DosageAdjustments = 'dosage_adjustments';
    case SideEffects = 'side_effects';
    case CommonSideEffects = 'common_side_effects';
    case RareSeriousSideEffects = 'rare_serious_side_effects';
    case LongTermEffects = 'long_term_effects';
    case Adr = 'adr';
    case Contraindications = 'contraindications';
    case DrugInteractions = 'drug_interactions';
    case PregnancyBreastfeeding = 'pregnancy_breastfeeding';
    case DrugProfileSummary = 'drug_profile_summary';
    case PopularCombinations = 'popular_combinations';
    case Precautions = 'precautions';

    public function label(): string
    {
        return match ($this) {
            self::Overview => 'Overview',
            self::Usage => 'Usage',
            self::AlternateNames => 'Alternate Names',
            self::HowItWorks => 'How It Works',
            self::Dosage => 'Dosage',
            self::StandardDosage => 'Standard Dosage',
            self::ClinicalUseCases => 'Clinical Use Cases',
            self::DosageAdjustments => 'Dosage Adjustments',
            self::SideEffects => 'Side Effects',
            self::CommonSideEffects => 'Common Side Effects',
            self::RareSeriousSideEffects => 'Rare but Serious Side Effects',
            self::LongTermEffects => 'Long-Term Effects',
            self::Adr => 'Adverse Drug Reactions (ADR)',
            self::Contraindications => 'Contraindications',
            self::DrugInteractions => 'Drug Interactions',
            self::PregnancyBreastfeeding => 'Pregnancy and Breastfeeding',
            self::DrugProfileSummary => 'Drug Profile Summary',
            self::PopularCombinations => 'Popular Combinations',
            self::Precautions => 'Precautions',
        };
    }
}
