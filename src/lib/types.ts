export type Identity = "age50" | "remote";

export type LifeConcept =
  | "coastal"
  | "mountain"
  | "city"
  | "outdoor"
  | "undecided";

export type Duration =
  | "try_then_stay"
  | "quarter"
  | "year"
  | "days_tourism"
  | "no_membership";

export type LivingWith = "solo" | "partner" | "friends" | "unsure";

export type Budget =
  | "under_20k"
  | "band_20_40"
  | "band_40_60"
  | "over_60k"
  | "see_first";

export type Priority =
  | "ocean_pace"
  | "community"
  | "health"
  | "remote_space"
  | "coffee_ritual"
  | "cheap_nights";

export type StartWhen = "within_month" | "within_quarter" | "within_half" | "undecided";

export type QuizOutcome = "pass" | "review" | "reject";

export type QuizAnswers = {
  identities: Identity[];
  life: LifeConcept;
  duration: Duration;
  livingWith: LivingWith;
  budget: Budget;
  priorities: Priority[];
  when: StartWhen;
  name?: string;
  phone?: string;
};

export type QuizResult = {
  outcome: QuizOutcome;
  reasons: string[];
  headline: string;
  body: string;
};

export type MemberPlanId = "seascape_list" | "quarter" | "year";

export type AuthProvider = "google" | "line" | "apple" | "email" | "sms";

export type OtpGateway = "smsgo" | "preview";

export type PendingOtp = {
  channel: "sms" | "email";
  destination: string;
  code?: string;
  codeHash?: string;
  salt?: string;
  serial?: string;
  gateway: OtpGateway;
  expiresAt: string;
};

export type SessionState = {
  id: string;
  name: string;
  phone: string;
  email: string;
  quizOutcome: QuizOutcome | null;
  quizReasons: string[];
  quizAnswers: QuizAnswers | null;
  authVerified: boolean;
  authProvider: AuthProvider | null;
  pendingOtp: PendingOtp | null;
  portalMemberId: string | null;
  memberIdentifier: string | null;
  portalHandoff: "linked" | "pending" | "unavailable" | null;
  isMember: boolean;
  memberPlan: MemberPlanId | null;
  membershipApplicationId: string | null;
  createdAt: string;
  updatedAt: string;
};

export type OrderType =
  | "membership_application"
  | "experience_request"
  | "coffee_order";

export type MembershipApplication = {
  id: string;
  type: "membership_application";
  sessionId: string;
  name: string;
  phone: string;
  email: string;
  planId: MemberPlanId;
  acceptContract: true;
  noInstallmentAck: true;
  status: "pending_contract" | "signed_member";
  createdAt: string;
};

export type ExperienceRequest = {
  id: string;
  type: "experience_request";
  sessionId: string;
  name: string;
  phone: string;
  guestCount: number;
  startDate: string;
  endDate: string;
  notes: string;
  status: "submitted";
  createdAt: string;
};

export type DeliveryMethod = "home" | "cvs";
export type Cadence = "once" | "biweekly" | "monthly";
export type PaymentMethod = "line_pay" | "apple_pay" | "cod";
export type SubscriptionPayMode = "per_shipment_cod" | "pay_all_now";
export type InvoiceType = "personal" | "company";

export type CartItem = {
  productId: string;
  name: string;
  unitPrice: number;
  qty: number;
};

export type CoffeeOrder = {
  id: string;
  type: "coffee_order";
  sessionId: string;
  items: CartItem[];
  gift: boolean;
  recipientName: string;
  recipientPhone: string;
  deliveryMethod: DeliveryMethod;
  address: string;
  cvsStore: string;
  cadence: Cadence;
  paymentMethod: PaymentMethod;
  subscriptionPayMode: SubscriptionPayMode | null;
  invoiceType: InvoiceType;
  taxId: string;
  companyName: string;
  shipmentCount: number;
  productSubtotal: number;
  shipmentGoodsAmount: number;
  shippingFee: number;
  payableNow: number;
  remainingNote: string;
  status: "mock_paid" | "mock_cod";
  createdAt: string;
};

export type StoreRecord =
  | MembershipApplication
  | ExperienceRequest
  | CoffeeOrder;
