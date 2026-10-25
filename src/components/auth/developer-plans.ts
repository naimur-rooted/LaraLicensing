export interface PlanOption {
  code: string;
  name: string;
  price: string;
  period: string;
  limits: string;
  badge?: string;
  isPopular?: boolean;
}

export const PLAN_OPTIONS: PlanOption[] = [
  {
    code: "STARTER",
    name: "Starter / Free",
    price: "$0",
    period: "forever",
    limits: "1 Product • 100 req/day",
    badge: "Free",
  },
  {
    code: "ESSENTIAL",
    name: "Essential",
    price: "$2.50",
    period: "/ mo",
    limits: "3 Products • 200 req/day",
  },
  {
    code: "PRO",
    name: "Pro Developer",
    price: "$5.00",
    period: "/ mo",
    limits: "15 Products • 1,000 req/day",
    badge: "Most Popular",
    isPopular: true,
  },
  {
    code: "MAX",
    name: "Max Enterprise",
    price: "$9.90",
    period: "/ mo",
    limits: "50 Products • 5,000 req/day",
    badge: "High Scale",
  },
];
