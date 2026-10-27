export interface BoundMachine {
  readonly hostname: string;
  readonly hardwareDigest: string;
  readonly boundAt: string;
}

export interface ShardLicense {
  readonly licenseId: number;
  readonly serialValue: string;
  readonly dealCode: string;
  readonly allowedSeats: number;
  readonly activeSeats: number;
  readonly isActive: boolean;
  readonly createdAt: string;
  readonly boundMachines: readonly BoundMachine[];
}

export interface ProductSelectItem {
  readonly ProductId: number;
  readonly productName: string;
  readonly productSlug: string;
}
