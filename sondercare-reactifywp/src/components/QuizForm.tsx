import React, { useState, useEffect, useCallback } from 'react';
import BedWidthSelection from './steps/BedWidthSelection';
import FinishPreference from './steps/FinishPreference';
import UserHeight from './steps/UserHeight';
import FallRisk from './steps/FallRisk';
import UserMobility from './steps/UserMobility';
import EatReadInBed from './steps/EatReadInBed';
import AccessorySelection from './steps/AccessorySelection';
import BeddingSelection from './steps/BeddingSelection';
import DeliveryLocation from './steps/DeliveryLocation';
import DeliveryPreferences from './steps/DeliveryPreferences';
import SetupInstallationOptions from './steps/SetupInstallationOptions';

const QuizForm = ({ setQuizResults }) => {
  const [currentStep, setCurrentStep] = useState(1);
  const [formData, setFormData] = useState({});
  const [error, setError] = useState(null);
  const totalSteps = 11;

  useEffect(() => {
    console.log('QuizForm mounted, currentStep:', currentStep);
  }, []);

  useEffect(() => {
    console.log('Current step changed to:', currentStep);
  }, [currentStep]);

  const updateFormData = useCallback((newData) => {
    setFormData(prevData => {
      const updatedData = { ...prevData, ...newData };
      console.log('Form data updated:', updatedData);
      return updatedData;
    });
  }, []);

  const nextStep = () => {
    if (currentStep < totalSteps) {
      setCurrentStep(prevStep => prevStep + 1);
    } else {
      handleSubmit();
    }
  };

  const prevStep = () => {
    if (currentStep > 1) {
      setCurrentStep(prevStep => prevStep - 1);
    }
  };

  const handleSubmit = () => {
    console.log('Form submitted', formData);
    const results = calculateRecommendations(formData);
    setQuizResults(results);
  };

  const calculateRecommendations = (data) => {
    let output = {};

    // Main Bed
    if (data.bedWidth) {
      const bedSeries = data.bedFinish === 'platinum' ? 'Platinum' : 'Premium';
      output.MainBed = { 
        name: `Aura™ ${bedSeries} ${data.bedWidth}"`, 
        price: data.bedWidth === '48' ? (bedSeries === 'Platinum' ? 9999 : 7999) : (bedSeries === 'Platinum' ? 7499 : 5999)
      };
    }

    // Mattress
    if (data.mobility) {
      let mattressName, mattressPrice;
      if (data.mobility === 'high_risk') {
        mattressName = `Alternating Pressure Low Air Loss Mattress (${data.bedWidth}")`;
        mattressPrice = data.bedWidth === '48' ? 3999 : 2999;
      } else if (data.mobility === 'stationary') {
        mattressName = `Comfort Pressure Redistribution Mattress`;
        mattressPrice = 799;
      } else {
        mattressName = `Dream Pressure Redistribution Mattress (${data.bedWidth}")`;
        mattressPrice = data.bedWidth === '48' ? 1399 : 1199;
      }
      output.Mattress = { name: mattressName, price: mattressPrice };
    }

    // Accessories
    output.Accessories = [];
    if (data.selectedAccessories) {
      data.selectedAccessories.forEach(accessoryId => {
        switch(accessoryId) {
          case 'helperBar':
            output.Accessories.push({ name: 'Overhead Helper Trapeze Bar', price: 369 });
            break;
          case 'batteryBackup':
            output.Accessories.push({ name: 'Battery Back-Up', price: 149 });
            break;
          case 'transportCart':
            output.Accessories.push({ name: 'Transport Cart', price: 199 });
            break;
          case 'railOrganizer':
            output.Accessories.push({ name: 'Rail Organizer', price: 89 });
            break;
          case 'railPads':
            output.Accessories.push({ name: 'Rail Pads', price: 99 });
            break;
        }
      });
    }

    // Conditional recommendations
    if (data.userHeight === '6_2_or_taller') {
      output.Accessories.push({ 
        name: `8" Length Extension Kit (${data.bedWidth}")`, 
        price: data.bedWidth === '48' ? 489 : 449 
      });
    }
    if (data.fallRisk === 'moderate' || data.fallRisk === 'high') {
      output.Accessories.push({ name: 'Auto Underbed Nightlight', price: 219 });
      output.Accessories.push({ name: 'Additional Rail Set (Pair)', price: 594 });
    }
    if (data.eatRead === 'yes') {
      output.Accessories.push({ name: 'Overbed Reading Light', price: 179 });
      output.Accessories.push({ name: 'Extra Large Overbed Table', price: 689 });
    }

    // Bedding
    output.Bedding = [];
    if (data.selectedBedding) {
      data.selectedBedding.forEach(beddingId => {
        switch(beddingId) {
          case 'sheetMicrofiber':
            output.Bedding.push({ name: 'Premium Microfiber Sheet Set (Twin XL)', price: 79 });
            break;
          case 'sheetOrganic39':
            output.Bedding.push({ name: 'Organic Cotton Sheet Set (Twin XL)', price: 149 });
            break;
          case 'sheetOrganic48':
            output.Bedding.push({ name: 'Extra Wide Sheet Set (for 48" Aura™ Bed)', price: 179 });
            break;
          case 'duvetWhite39':
          case 'duvetGray39':
            output.Bedding.push({ name: `Duvet & Duvet Cover (${beddingId.includes('White') ? 'White' : 'Grey'})`, price: 469 });
            break;
          case 'duvetWhite48':
          case 'duvetGray48':
            output.Bedding.push({ name: `Duvet & Duvet Cover (${beddingId.includes('White') ? 'White' : 'Grey'})`, price: 529 });
            break;
          case 'adjustablePillow':
            output.Bedding.push({ name: 'Heavenly Adjustable Pillow', price: 189 });
            break;
          case 'mattressCover':
            output.Bedding.push({ 
              name: `Fluid-Proof Mattress Cover${data.bedWidth === '48' ? ' (Wide)' : ''}`, 
              price: data.bedWidth === '48' ? 199 : 169 
            });
            break;
        }
      });
    }

    // Shipping
    output.Shipping = [];
    if (data.deliveryLocation === 'alaska_hawaii') {
      output.Shipping.push({ name: 'Additional Shipping – HI/AK', price: 899 });
    }
    if (data.deliveryOption === 'drop_ship') {
      output.Shipping.push({ name: 'Drop-Ship Delivery (No Installation)', price: 349 });
    } else if (data.deliveryOption === 'setup_installation') {
      const setupOption = data.setupInstallationOption;
      const setupDetails = {
        standard: { name: 'Standard Set-up & Installation (10-21 business days)', price: 499 },
        express: { name: 'Express Set-up & Installation (4-9 business days)', price: 699 },
        rush: { name: 'Rush Set-up & Installation (1-3 business days)', price: 899 }
      };
      output.Shipping.push(setupDetails[setupOption]);
    }

    return output;
  };

  const renderStep = () => {
    const commonProps = {
      formData,
      updateFormData,
      nextStep,
      prevStep,
      currentStep,
      totalSteps,
      handleSubmit
    };

    try {
      switch (currentStep) {
        case 1: return <BedWidthSelection {...commonProps} />;
        case 2: return <FinishPreference {...commonProps} />;
        case 3: return <UserHeight {...commonProps} />;
        case 4: return <FallRisk {...commonProps} />;
        case 5: return <EatReadInBed {...commonProps} />;
        case 6: return <UserMobility {...commonProps} />;
        case 7: return <AccessorySelection {...commonProps} />;
        case 8: return <BeddingSelection {...commonProps} />;
        case 9: return <DeliveryLocation {...commonProps} />;
        case 10: return <DeliveryPreferences {...commonProps} />;
        case 11: return <SetupInstallationOptions {...commonProps} />;
        default: throw new Error(`Unknown step: ${currentStep}`);
      }
    } catch (err) {
      console.error('Error rendering step:', err);
      setError(err.message);
      return null;
    }
  };

  if (error) {
    return <div className="text-red-500">Error: {error}</div>;
  }

  return (
    <div className="p-4 pb-6 h-[520px] flex flex-col">
      {renderStep()}
    </div>
  );
};

export default QuizForm;
