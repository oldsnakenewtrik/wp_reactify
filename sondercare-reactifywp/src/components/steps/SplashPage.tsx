import React from 'react';

const SplashPage = ({ handleNext }) => {
  return (
    <div className="text-center p-8">
      <div className="mb-8">
        <h2 className="text-3xl font-bold text-[#25385b] mb-4">
          Find Your Perfect Bed
        </h2>
        <p className="text-lg text-gray-600 mb-6">
          Answer a few questions to get personalized recommendations for your SonderCare bed setup.
        </p>
      </div>
      
      <div className="mb-8">
        <img 
          src="https://www.sondercare.com/wp-content/uploads/2024/10/bed-selector-hero.jpg"
          alt="SonderCare Bed"
          className="w-full max-w-md mx-auto rounded-lg shadow-lg"
          onError={(e) => {
            e.target.style.display = 'none';
          }}
        />
      </div>
      
      <button 
        onClick={handleNext}
        className="bg-[#25385b] text-white px-8 py-4 rounded-lg text-xl font-semibold hover:bg-[#1e2d47] transition-colors"
      >
        Start Bed Selection
      </button>
    </div>
  );
};

export default SplashPage;