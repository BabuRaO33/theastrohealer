export interface PlanetDetail {
  name: string;
  sign: string;
  sign_id: number;
  house: number;
  degree: number;
  minute: number;
  retrograde: boolean;
  lord: string;
  nakshatra?: string;
  strength?: number;
}

export interface ChatMessage {
  role: "user" | "model";
  text: string;
  timestamp: number;
}

export interface UserSession {
  email: string;
  name: string;
  dob: string;
  tob: string;
  place: string;
  lat: number;
  lon: number;
  timezone: number;
  coins: number;
  plan: "free" | "premium";
  questionsAsked: number;
  chatHistory: ChatMessage[];
  role?: "user" | "admin";
}

export interface DailyForecast {
  prediction: string;
  sign: string;
}
