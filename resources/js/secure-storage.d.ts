export interface BridgeError extends Error {
  code?: string;
}

export interface SetResult {
  success: true;
}

export interface GetResult {
  value: string;
}

export interface DeleteResult {
  success: true;
}

export declare const SecureStorage: {
  set(key: string, value?: string | null): Promise<SetResult>;
  get(key: string): Promise<GetResult>;
  has(key: string): Promise<boolean>;
  delete(key: string): Promise<DeleteResult>;
};

export default SecureStorage;
