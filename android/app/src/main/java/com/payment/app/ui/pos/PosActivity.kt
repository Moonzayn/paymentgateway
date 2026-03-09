package com.payment.app.ui.pos

import android.graphics.Bitmap
import android.os.Bundle
import android.view.View
import android.widget.Toast
import androidx.activity.viewModels
import androidx.appcompat.app.AlertDialog
import androidx.appcompat.app.AppCompatActivity
import androidx.recyclerview.widget.LinearLayoutManager
import com.google.zxing.BarcodeFormat
import com.google.zxing.qrcode.QRCodeWriter
import com.payment.app.R
import com.payment.app.data.model.PosItem
import com.payment.app.databinding.ActivityPosBinding
import com.payment.app.util.Resource
import dagger.hilt.android.AndroidEntryPoint

@AndroidEntryPoint
class PosActivity : AppCompatActivity() {

    private lateinit var binding: ActivityPosBinding
    private val viewModel: PosViewModel by viewModels()
    private lateinit var productAdapter: PosProductAdapter
    private lateinit var cartAdapter: PosCartAdapter
    private var currentReference: String? = null
    private var currentQrisString: String? = null

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityPosBinding.inflate(layoutInflater)
        setContentView(binding.root)

        setSupportActionBar(binding.toolbar)
        supportActionBar?.setDisplayHomeAsUpEnabled(true)

        setupViews()
        observeData()
    }

    private fun setupViews() {
        // Products RecyclerView
        productAdapter = PosProductAdapter { item ->
            viewModel.addToCart(item)
        }
        binding.rvProducts.apply {
            layoutManager = LinearLayoutManager(this@PosActivity)
            adapter = productAdapter
        }

        // Cart RecyclerView
        cartAdapter = PosCartAdapter(
            onQuantityChanged = { index, qty -> viewModel.updateQuantity(index, qty) },
            onRemove = { index -> viewModel.removeFromCart(index) }
        )
        binding.rvCart.apply {
            layoutManager = LinearLayoutManager(this@PosActivity)
            adapter = cartAdapter
        }

        // Load sample products
        productAdapter.submitList(viewModel.getSampleProducts())

        // Button listeners
        binding.btnQris.setOnClickListener {
            viewModel.createQrisPayment()
        }

        binding.btnCash.setOnClickListener {
            showCashPaymentDialog()
        }

        binding.btnClear.setOnClickListener {
            viewModel.clearCart()
        }

        binding.btnCancelQris.setOnClickListener {
            viewModel.stopPolling()
            hideQrisDialog()
        }
    }

    private fun observeData() {
        viewModel.cart.observe(this) { cart ->
            cartAdapter.submitList(cart.toList())
            binding.tvEmptyCart.visibility = if (cart.isEmpty()) View.VISIBLE else View.GONE
        }

        viewModel.total.observe(this) { total ->
            binding.tvTotal.text = "Rp ${String.format("%,d", total).replace(",", ".")}"
            binding.btnQris.isEnabled = total > 0
            binding.btnCash.isEnabled = total > 0
        }

        viewModel.qrisState.observe(this) { state ->
            when (state) {
                is Resource.Loading -> {
                    binding.progressBar.visibility = View.VISIBLE
                }
                is Resource.Success -> {
                    binding.progressBar.visibility = View.GONE
                    state.data?.let { response ->
                        if (response.success) {
                            currentReference = response.reference
                            currentQrisString = response.qrisString
                            showQrisDialog(response.qrisString ?: "", response.expiredAt ?: "")
                            response.reference?.let { viewModel.startPolling(it) }
                        } else {
                            Toast.makeText(this, response.message, Toast.LENGTH_SHORT).show()
                        }
                    }
                }
                is Resource.Error -> {
                    binding.progressBar.visibility = View.GONE
                    Toast.makeText(this, state.message, Toast.LENGTH_SHORT).show()
                }
            }
        }

        viewModel.qrisStatus.observe(this) { state ->
            when (state) {
                is Resource.Success -> {
                    if (state.data?.paid == true) {
                        viewModel.stopPolling()
                        // Save transaction
                        viewModel.saveTransaction(
                            metodeBayar = "qris",
                            uangDiberikan = null,
                            qrisReference = currentReference,
                            qrisString = currentQrisString
                        )
                    }
                }
                else -> {}
            }
        }

        viewModel.saveState.observe(this) { state ->
            when (state) {
                is Resource.Loading -> {
                    binding.progressBar.visibility = View.VISIBLE
                }
                is Resource.Success -> {
                    binding.progressBar.visibility = View.GONE
                    hideQrisDialog()

                    state.data?.let { response ->
                        if (response.success) {
                            Toast.makeText(this, "Transaksi berhasil!", Toast.LENGTH_SHORT).show()
                            response.invoice?.let { showReceiptDialog(it) }
                            viewModel.clearCart()
                        } else {
                            Toast.makeText(this, response.message, Toast.LENGTH_SHORT).show()
                        }
                    }
                }
                is Resource.Error -> {
                    binding.progressBar.visibility = View.GONE
                    Toast.makeText(this, state.message, Toast.LENGTH_SHORT).show()
                }
            }
        }
    }

    private fun showQrisDialog(qrisString: String, expiredAt: String) {
        binding.layoutQris.visibility = View.VISIBLE

        try {
            val writer = QRCodeWriter()
            val bitMatrix = writer.encode(qrisString, BarcodeFormat.QR_CODE, 512, 512)
            val bitmap = Bitmap.createBitmap(512, 512, Bitmap.Config.RGB_565)
            for (x in 0 until 512) {
                for (y in 0 until 512) {
                    bitmap.setPixel(x, y, if (bitMatrix[x, y]) android.graphics.Color.BLACK else android.graphics.Color.WHITE)
                }
            }
            binding.ivQris.setImageBitmap(bitmap)
        } catch (e: Exception) {
            Toast.makeText(this, "Gagal generate QR", Toast.LENGTH_SHORT).show()
        }

        binding.tvExpiredAt.text = "Kedaluwarsa: $expiredAt"
    }

    private fun hideQrisDialog() {
        binding.layoutQris.visibility = View.GONE
    }

    private fun showCashPaymentDialog() {
        val total = viewModel.total.value ?: 0

        val dialogView = layoutInflater.inflate(R.layout.dialog_cash_payment, null)
        val etUang = dialogView.findViewById<android.widget.EditText>(R.id.et_uang_diberikan)
        val tvTotal = dialogView.findViewById<android.widget.TextView>(R.id.tv_total)
        val tvKembalian = dialogView.findViewById<android.widget.TextView>(R.id.tv_kembalian)

        tvTotal.text = "Total: Rp ${String.format("%,d", total).replace(",", ".")}"

        etUang.addTextChangedListener(object : android.text.TextWatcher {
            override fun beforeTextChanged(s: CharSequence?, start: Int, count: Int, after: Int) {}
            override fun onTextChanged(s: CharSequence?, start: Int, before: Int, count: Int) {}
            override fun afterTextChanged(s: android.text.Editable?) {
                val uang = s.toString().toIntOrNull() ?: 0
                val kembalian = uang - total
                tvKembalian.text = if (kembalian >= 0) "Kembalian: Rp ${String.format("%,d", kembalian).replace(",", ".")}" else "Kurang: Rp ${String.format("%,d", -kembalian).replace(",", ".")}"
            }
        })

        AlertDialog.Builder(this)
            .setTitle("Pembayaran Tunai")
            .setView(dialogView)
            .setPositiveButton("Bayar") { _, _ ->
                val uangDiberikan = etUang.text.toString().toIntOrNull() ?: 0
                if (uangDiberikan >= total) {
                    viewModel.saveTransaction(
                        metodeBayar = "cash",
                        uangDiberikan = uangDiberikan,
                        qrisReference = null,
                        qrisString = null
                    )
                } else {
                    Toast.makeText(this, "Uang tidak cukup", Toast.LENGTH_SHORT).show()
                }
            }
            .setNegativeButton("Batal", null)
            .show()
    }

    private fun showReceiptDialog(invoice: String) {
        viewModel.loadTransactionDetail(invoice)
    }

    override fun onSupportNavigateUp(): Boolean {
        onBackPressedDispatcher.onBackPressed()
        return true
    }

    override fun onDestroy() {
        super.onDestroy()
        viewModel.stopPolling()
    }
}
