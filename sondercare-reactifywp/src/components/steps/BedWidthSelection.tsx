import React, { useState } from 'react';

const BedWidthSelection = ({ formData, updateFormData, nextStep, currentStep, totalSteps }) => {
  const [selectedWidth, setSelectedWidth] = useState(formData.bedWidth || '');

  const widthOptions = [
    { id: '39', label: '39" Standard Width', description: 'Perfect for most users' },
    { id: '48', label: '48" Extra Wide', description: 'For larger users or couples' }
  ];

  const handleSelection = (width) => {
    setSelectedWidth(width);
    updateFormData({ bedWidth: width });
  };

  const handleNext = () => {
    if (selectedWidth) {
      nextStep();
    }
  };

  return (
    <div className="flex flex-col h-full">
      <div className="mb-4">
        <div className="flex justify-between items-center mb-2">
          <span className="text-sm text-gray-500">Step {currentStep} of {totalSteps}</span>
          <div className="w-32 bg-gray-200 rounded-full h-2">
            <div 
              className="bg-[#25385b] h-2 rounded-full transition-all duration-300"
              style={{ width: `${(currentStep / totalSteps) * 100}%` }}
            ></div>
          </div>
        </div>
      </div>

      <h2 className="text-2xl font-bold mb-6 text-center text-[#25385b]">
        What bed width do you prefer?
      </h2>
      
      <div className="flex-grow flex flex-col justify-center">
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
          {widthOptions.map((option) => (
            <button
              key={option.id}
              onClick={() => handleSelection(option.id)}
              className={`p-6 border-2 rounded-lg transition-all ${
                selectedWidth === option.id 
                  ? 'border-[#25385b] bg-[#25385b] text-white' 
                  : 'border-gray-300 hover:border-[#25385b]'
              }`}
            >
              <div className="text-lg font-semibold mb-2">{option.label}</div>
              <div className="text-sm opacity-80">{option.description}</div>
            </button>
          ))}
        </div>
      </div>

      <div className="flex justify-between mt-6">
        <div></div>
        <button 
          onClick={handleNext}
          disabled={!selectedWidth}
          className="bg-[#25385b] text-white px-6 py-3 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed hover:bg-[#1e2d47] transition-colors"
        >
          Next Step
        </button>
      </div>
    </div>
  );
};

export default BedWidthSelection;